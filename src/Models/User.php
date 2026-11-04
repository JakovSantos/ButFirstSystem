<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Login account. Admins have no employee_id; employees sign in with their Employee ID
 * and can only see their own payroll.
 */
final class User extends Model
{
    protected $table = 'users';

    /** @var list<string> */
    protected $fillable = ['username', 'password_hash', 'role', 'employee_id'];

    /** @var list<string> */
    protected $hidden = ['password_hash'];

    public function payroll(): BelongsTo
    {
        return $this->belongsTo(Payroll::class, 'employee_id', 'employee_id');
    }

    public static function findByUsername(string $username): ?self
    {
        return self::query()->whereRaw('LOWER(username) = ?', [mb_strtolower(trim($username))])->first();
    }
}
