<?php

namespace Dyrynda\Database\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

trait CascadeSoftDeletes
{
    /**
     * Boot the trait.
     *
     * Listen for the deleting event of a soft deleting model, and run
     * the delete operation for any configured relationship methods.
     *
     * @throws \LogicException
     */
    protected static function bootCascadeSoftDeletes()
    {
        static::deleting(function ($model) {
            $model->validateCascadingSoftDelete();

            $model->runCascadingDeletes();
        });


        static::restoring(function ($model) {
            $model->validateCascadingSoftDelete();

            $model->runRestoreCascadingDeletes();
        });
    }


    /**
     * Validate that the calling model is correctly setup for cascading soft deletes.
     *
     * @throws \Dyrynda\Database\Support\CascadeSoftDeleteException
     */
    protected function validateCascadingSoftDelete()
    {
        if (! $this->implementsSoftDeletes()) {
            throw CascadeSoftDeleteException::softDeleteNotImplemented(get_called_class());
        }

        if ($invalidCascadingRelationships = $this->hasInvalidCascadingRelationships()) {
            throw CascadeSoftDeleteException::invalidRelationships($invalidCascadingRelationships);
        }
    }


    /**
     * Run the cascading soft delete for this model.
     *
     * @return void
     */
    protected function runCascadingDeletes()
    {
        foreach ($this->getActiveCascadingDeletes() as $relationship) {
            $this->cascadeSoftDeletes($relationship);
        }
    }



    /**
     * Run the restore cascading deletes process.
     *
     * @return void
     */
    protected function runRestoreCascadingDeletes()
    {
        foreach ($this->getDeletedCascadingDeletes() as $relationship) {
            $this->cascadeRestores($relationship);
        }
    }


    /**
     * Cascade delete the given relationship on the given mode.
     *
     * @param  string  $relationship
     * @return return
     */
    protected function cascadeSoftDeletes($relationship)
    {
        $delete = $this->forceDeleting ? 'forceDelete' : 'delete';

        foreach ($this->{$relationship}()->get() as $model) {
            isset($model->pivot) ? $model->pivot->{$delete}() : $model->{$delete}();
        }
    }



    /**
     * Cascade restores the related models for a given relationship.
     *
     * @param string $relationship The name of the relationship.
     * @return void
     */
    protected function cascadeRestores($relationship)
    {
        foreach ($this->{$relationship}()->onlyTrashed()->get() as $model) {
            $model->restore();
        }
    }


    /**
     * Determine if the current model implements soft deletes.
     *
     * @return bool
     */
    protected function implementsSoftDeletes()
    {
        return method_exists($this, 'runSoftDelete');
    }


    /**
     * Determine if the current model has any invalid cascading relationships defined.
     *
     * A relationship is considered invalid when the method does not exist, or the relationship
     * method does not return an instance of Illuminate\Database\Eloquent\Relations\Relation.
     *
     * @return array
     */
    protected function hasInvalidCascadingRelationships()
    {
        return array_filter($this->getCascadingDeletes(), function ($relationship) {
            return ! method_exists($this, $relationship) || ! $this->{$relationship}() instanceof Relation;
        });
    }


    /**
     * Fetch the defined cascading soft deletes for this model.
     *
     * @return array
     */
    protected function getCascadingDeletes()
    {
        return isset($this->cascadeDeletes) ? (array) $this->cascadeDeletes : [];
    }


    /**
     * For the cascading deletes defined on the model, return only those that are not null.
     *
     * @return array
     */
    protected function getActiveCascadingDeletes()
    {
        return array_filter($this->getCascadingDeletes(), function ($relationship) {
            return $this->{$relationship}()->exists();
        });
    }


    /**
     * Retrieves the deleted cascading deletes.
     *
     * @return mixed
     */
    protected function getDeletedCascadingDeletes()
    {
        return $this->getCascadingDeletes();
    }
}
