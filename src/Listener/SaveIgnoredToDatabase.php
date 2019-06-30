<?php

/*
 * This file is part of fof/ignore-users.
 *
 * Copyright (c) 2019 FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\IgnoreUsers\Listener;

use Carbon\Carbon;
use Flarum\User\AssertPermissionTrait;
use Flarum\User\Event\Saving;
use FoF\IgnoreUsers\Event\Ignoring;
use FoF\IgnoreUsers\Event\Unignoring;
use Illuminate\Contracts\Events\Dispatcher;

class SaveIgnoredToDatabase
{
    use AssertPermissionTrait;

    /**
     * @var Dispatcher
     */
    protected $events;

    /**
     * @param Dispatcher $events
     */
    public function __construct(Dispatcher $events)
    {
        $this->events = $events;
    }

    public function handle(Saving $event)
    {
        $attributes = array_get($event->data, 'attributes', []);

        if (array_key_exists('ignored', $attributes)) {
            $user = $event->user;
            $actor = $event->actor;

            if ($user->id === $actor->id) {
                return;
            }

            /*
              // TODO check if user allowed to be ignored.
              $this->assertCan($actor, 'ignore', $user);
            */

            $ignored = !!$attributes['ignored'];
            $changed = false;
            $exists = $actor->ignoredUsers()->where('ignored_user_id', $user->id)->exists();

            if ($ignored) {
                if (!$exists) {
                    $this->events->dispatch(new Ignoring($user, $actor));
                    $actor->ignoredUsers()->attach($user, ['ignored_at' => Carbon::now()]);
                    $changed = true;
                }
            } elseif ($exists) {
                $this->events->dispatch(new Unignoring($user, $actor));
                $actor->ignoredUsers()->detach($user);
                $changed = true;
            }

            if ($changed) {
                $actor->load('ignoredUsers');
            }
        }
    }
}
