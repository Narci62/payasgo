<?php

namespace Tests\Unit;

use App\Helpers\PermissionHelper;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PermissionHelperTest extends TestCase
{
    #[Test]
    public function it_returns_french_label_for_known_permission(): void
    {
        $this->assertSame('Voir les clients', PermissionHelper::getLabel('view-clients'));
    }

    #[Test]
    public function it_returns_french_label_for_manage_roles(): void
    {
        $this->assertSame('Gérer les rôles et permissions', PermissionHelper::getLabel('manage-roles'));
    }

    #[Test]
    public function it_returns_slug_when_permission_is_unknown(): void
    {
        $this->assertSame('unknown-permission', PermissionHelper::getLabel('unknown-permission'));
    }

    #[Test]
    public function it_returns_all_22_labels(): void
    {
        $labels = PermissionHelper::getLabels();

        $this->assertCount(23, $labels);
        $this->assertArrayHasKey('view-clients', $labels);
        $this->assertArrayHasKey('view-sales-report', $labels);
    }

    #[Test]
    public function it_covers_all_permission_crud_labels(): void
    {
        $labels = PermissionHelper::getLabels();

        $this->assertSame('Créer des clients', $labels['create-clients']);
        $this->assertSame('Modifier des clients', $labels['edit-clients']);
        $this->assertSame('Supprimer des clients', $labels['delete-clients']);
    }
}
