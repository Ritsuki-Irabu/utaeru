<?php

namespace App\Policies;

use App\Models\MySong;
use App\Models\User;

class MySongPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // ログイン済みなら「自分の一覧」は見てよい
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, MySong $mySong): bool
    {
        // 1件を見るなら本人のものだけ
        return $user->id === $mySong->user_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // ログイン済みならマイリスト追加してよい
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, MySong $mySong): bool
    {
        // 自分のmy_songだけ更新できる
        return $user->id === $mySong->user_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, MySong $mySong): bool
    {
        // 自分のmy_songだけ削除できる
        return $user->id === $mySong->user_id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, MySong $mySong): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, MySong $mySong): bool
    {
        return false;
    }
}
