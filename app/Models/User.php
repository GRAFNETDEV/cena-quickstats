<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * User Model
 */
class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $table = 'users';

    protected $fillable = [
        'code',
        'nom',
        'prenom',
        'npi', // ✅ Présent
        'email',
        'telephone',
        'password',
        'photo',
        'statut',
        'derniere_connexion',
        'ip_derniere_connexion',
        'email_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'derniere_connexion' => 'datetime',
        'password' => 'hashed',
    ];

    // ... Relations existantes ...
    public function affectations(): HasMany
    {
        return $this->hasMany(UserAffectation::class, 'user_id');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(UserSession::class, 'user_id');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class, 'user_id');
    }

    /**
     * ✅ Vérifie si l'utilisateur est Super Admin
     */
    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super_admin');
    }

    /**
     * ✅ Vérifier si l'utilisateur a un ou plusieurs rôles (string ou array)
     * Prend en compte uniquement les affectations ACTIVES.
     */
    public function hasRole(string|array $roleSlugs): bool
    {
        $roleSlugs = is_array($roleSlugs) ? $roleSlugs : [$roleSlugs];

        return $this->affectations()
            ->where('actif', true)
            ->whereHas('role', fn($q) => $q->whereIn('slug', $roleSlugs))
            ->exists();
    }

    /**
     * ✅ Alias pour vérifier plusieurs rôles
     */
    public function hasAnyRole(array $roleSlugs): bool
    {
        return $this->hasRole($roleSlugs);
    }

    /**
     * ✅ Vérifier si l'utilisateur a au moins une des permissions demandées
     * (Via ses rôles actifs)
     */
    public function hasAnyPermission(array $permissionSlugs): bool
    {
        // On récupère les affectations actives avec leur rôle
        $affectations = $this->affectations()->where('actif', true)->with('role.permissions')->get();

        foreach ($affectations as $aff) {
            // Si le rôle a une des permissions demandées
            if ($aff->role && $aff->role->permissions->whereIn('slug', $permissionSlugs)->isNotEmpty()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Garde la méthode unitaire existante pour compatibilité, mais via la logique robuste
     */
    public function hasPermission(string $permissionSlug): bool
    {
        return $this->hasAnyPermission([$permissionSlug]);
    }

    /**
     * ✅ Vérifier l'accès géographique (Champs réels: niveau_affectation)
     */
    public function hasAccessToNiveau(string $niveau, int $niveauId): bool
    {
        return $this->affectations()
            ->where('actif', true)
            ->where('niveau_affectation', $niveau)
            ->where('niveau_affectation_id', $niveauId)
            ->exists();
    }

    /**
     * Vérifier si l'utilisateur a accès à une élection
     */
    public function hasAccessToElection(int $electionId): bool
    {
        // Accès global (pas d'élection spécifiée = toutes)
        $hasGlobalAccess = $this->affectations()
            ->where('actif', true)
            ->whereNull('election_id')
            ->exists();

        if ($hasGlobalAccess) {
            return true;
        }

        // Accès spécifique
        return $this->affectations()
            ->where('actif', true)
            ->where('election_id', $electionId)
            ->exists();
    }

    // ... Helpers existants (getRoles, getPermissions, Scopes, Accessors) ...

    public function getRoles()
    {
        return $this->affectations->where('actif', true)->map(fn($aff) => $aff->role);
    }

    public function getPermissions()
    {
        $permissions = collect();
        foreach ($this->affectations()->where('actif', true)->with('role.permissions')->get() as $aff) {
            if ($aff->role) {
                $permissions = $permissions->merge($aff->role->permissions);
            }
        }
        return $permissions->unique('id');
    }

    public function scopeActive($query)
    {
        return $query->where('statut', 'actif');
    }

    public function scopeByStatut($query, string $statut)
    {
        return $query->where('statut', $statut);
    }

    public function getNomCompletAttribute(): string
    {
        return trim($this->prenom . ' ' . $this->nom);
    }

    public function getActiveElectionId(): int
    {
        $election = \App\Models\Election::orderBy('id')->first();
        if (!$election) {
            throw new \Exception('Aucune élection disponible');
        }
        return $election->id;
    }
}