<?php

namespace App\Traits;

trait HasPermissions
{
    /**
     * Check if user has a specific permission
     */
    public function hasPermission($permission): bool
    {
        $role = $this->role ?? null;
        
        if (!$role) {
            return false;
        }
        
        $permissions = config('permissions.roles.' . $role . '.permissions', []);
        
        return $permissions[$permission] ?? false;
    }

    /**
     * Check if user has any of the provided permissions
     */
    public function hasAnyPermission(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Check if user has all of the provided permissions
     */
    public function hasAllPermissions(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if (!$this->hasPermission($permission)) {
                return false;
            }
        }
        
        return true;
    }
}

