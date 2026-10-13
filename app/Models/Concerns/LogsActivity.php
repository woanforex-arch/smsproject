<?php

namespace App\Models\Concerns;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Records create / update / delete events into the activity log.
 *
 * Models can override activityLogIgnore() to keep noisy or bulky attributes
 * (gateway payloads, login timestamps, secrets) out of the stored snapshot.
 */
trait LogsActivity
{
    public static function bootLogsActivity(): void
    {
        static::created(fn (Model $model) => $model->recordActivity('created'));
        static::updated(fn (Model $model) => $model->recordActivity('updated'));
        static::deleted(fn (Model $model) => $model->recordActivity('deleted'));
    }

    protected function recordActivity(string $event): void
    {
        $changes = $this->activityChanges($event);

        // An update that only touched ignored columns is not worth logging.
        if ($event === 'updated' && $changes === []) {
            return;
        }

        ActivityLog::record($event, $this, $this->activityDescription($event), $changes);
    }

    /**
     * Attributes to leave out of the activity snapshot.
     *
     * @return array<int, string>
     */
    protected function activityLogIgnore(): array
    {
        return [];
    }

    /** @return array<string, mixed> */
    protected function activityChanges(string $event): array
    {
        $ignored = array_values(array_unique(array_merge(
            $this->getHidden(),
            $this->activityLogIgnore(),
            ['updated_at'],
        )));

        if ($event === 'updated') {
            $changed = array_diff_key($this->getChanges(), array_flip($ignored));

            if ($changed === []) {
                return [];
            }

            return [
                'before' => array_intersect_key($this->getOriginal(), $changed),
                'after' => $changed,
            ];
        }

        return [
            'attributes' => array_diff_key($this->attributesToArray(), array_flip($ignored)),
        ];
    }

    protected function activityDescription(string $event): string
    {
        return ucfirst($event).' '.class_basename($this).': '.$this->activityLabel();
    }

    /** A short, human-readable label for this record. */
    public function activityLabel(): string
    {
        foreach (['title', 'name', 'reference', 'tx_ref', 'key', 'to_phone', 'attendee_name'] as $attribute) {
            $value = $this->getAttribute($attribute);

            if (is_string($value) && trim($value) !== '') {
                return $value;
            }
        }

        return '#'.$this->getKey();
    }
}
