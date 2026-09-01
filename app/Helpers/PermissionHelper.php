<?php

namespace App\Helpers;

class PermissionHelper
{
    private const LABELS = [
        'view-clients' => 'Voir les clients',
        'create-clients' => 'Créer des clients',
        'edit-clients' => 'Modifier des clients',
        'delete-clients' => 'Supprimer des clients',
        'view-devices' => 'Voir les appareils',
        'create-devices' => 'Créer des appareils',
        'edit-devices' => 'Modifier des appareils',
        'delete-devices' => 'Supprimer des appareils',
        'view-financing-plans' => 'Voir les plans de financement',
        'create-financing-plans' => 'Créer des plans de financement',
        'edit-financing-plans' => 'Modifier des plans de financement',
        'delete-financing-plans' => 'Supprimer des plans de financement',
        'view-phones' => 'Voir les téléphones',
        'create-phones' => 'Créer des téléphones',
        'edit-phones' => 'Modifier des téléphones',
        'delete-phones' => 'Supprimer des téléphones',
        'view-users' => 'Voir les utilisateurs',
        'create-users' => 'Créer des utilisateurs',
        'edit-users' => 'Modifier des utilisateurs',
        'delete-users' => 'Supprimer des utilisateurs',
        'manage-roles' => 'Gérer les rôles et permissions',
        'view-dashboard' => 'Voir le tableau de bord',
        'view-sales-report' => 'Voir le rapport de ventes',
    ];

    public static function getLabel(string $permission): string
    {
        return self::LABELS[$permission] ?? $permission;
    }

    public static function getLabels(): array
    {
        return self::LABELS;
    }
}
