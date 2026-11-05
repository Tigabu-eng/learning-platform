<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function setRole(User $actor, User $target, string $role)
    {
        return $this->actingAs($actor)->from(route('admin.users'))->put(route('admin.users.update', $target), ['role' => $role]);
    }

    public function test_super_admins_have_every_admin_power(): void
    {
        $super = User::factory()->create(['role' => 'super_admin']);
        $course = Course::create(['instructor_id' => User::factory()->create(['role' => 'instructor'])->id, 'title' => 'C']);
        $this->actingAs($super)->get(route('admin.dashboard'))->assertOk();
        $this->get(route('admin.users'))->assertOk()->assertSee('As a super admin');
        $this->get(route('studio.dashboard'))->assertOk();
        $this->get(route('studio.curriculum', $course))->assertOk();
        $this->assertTrue($super->isAdmin() && $super->canTeach() && $course->canBeManagedBy($super));
    }

    public function test_super_admin_can_make_and_remove_admins(): void
    {
        $super = User::factory()->create(['role' => 'super_admin']);
        $zen = User::factory()->create(['role' => 'student', 'name' => 'Zen']);

        $this->setRole($super, $zen, 'admin')->assertSessionHas('status', 'Zen is now an admin.');
        $this->assertSame('admin', $zen->fresh()->role);
        $this->setRole($super, $zen, 'super_admin')->assertSessionHas('status', 'Zen is now a super admin.');
        $this->setRole($super, $zen, 'student')->assertSessionHas('status', 'Zen is now a student.');
        $this->assertSame('student', $zen->fresh()->role);
    }

    public function test_admins_cannot_touch_admin_roles(): void
    {
        $super = User::factory()->create(['role' => 'super_admin']);
        $admin = User::factory()->create(['role' => 'admin']);
        $otherAdmin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student']);

        // Students and instructors: fine.
        $this->setRole($admin, $student, 'instructor')->assertSessionHasNoErrors();
        $this->assertSame('instructor', $student->fresh()->role);

        // Promoting to admin or super admin: no.
        $this->setRole($admin, $student, 'admin')->assertSessionHasErrors(['role' => 'Only a super admin can make or remove admins.']);
        $this->setRole($admin, $student, 'super_admin')->assertSessionHasErrors('role');
        // Demoting other admins or the super admin: no.
        $this->setRole($admin, $otherAdmin, 'student')->assertSessionHasErrors('role');
        $this->setRole($admin, $super, 'student')->assertSessionHasErrors('role');

        $this->assertSame(['instructor', 'admin', 'super_admin'], [$student->fresh()->role, $otherAdmin->fresh()->role, $super->fresh()->role]);

        // The page shows admins as fixed badges, with no role menu.
        $this->actingAs($admin)->get(route('admin.users'))->assertOk()
            ->assertSee('Only a super admin can make or remove admins.')
            ->assertDontSee('id="r'.$super->id.'"', false)
            ->assertSee('id="r'.$student->id.'"', false);
    }

    public function test_nobody_can_change_their_own_role(): void
    {
        $super = User::factory()->create(['role' => 'super_admin']);
        $admin = User::factory()->create(['role' => 'admin']);
        $this->setRole($super, $super, 'admin')->assertSessionHasErrors('role');
        $this->setRole($admin, $admin, 'student')->assertSessionHasErrors('role');
        $this->assertSame(['super_admin', 'admin'], [$super->fresh()->role, $admin->fresh()->role]);
    }

    public function test_unknown_roles_are_rejected_and_students_are_kept_out(): void
    {
        $super = User::factory()->create(['role' => 'super_admin']);
        $student = User::factory()->create();
        $this->setRole($super, $student, 'owner')->assertSessionHasErrors('role');
        $this->actingAs($student)->put(route('admin.users.update', $super), ['role' => 'student'])->assertForbidden();
        $this->assertSame('super_admin', $super->fresh()->role);
    }

    public function test_super_admin_login_lands_on_admin_dashboard(): void
    {
        User::factory()->create(['role' => 'super_admin', 'email' => 'owner@example.com', 'password' => 'secret-pass']);
        $this->post(route('login'), ['email' => 'owner@example.com', 'password' => 'secret-pass'])->assertRedirect(route('admin.dashboard'));
    }

    public function test_console_command_makes_a_super_admin(): void
    {
        $u = User::factory()->create(['email' => 'owner@example.com', 'name' => 'Owner']);
        $this->artisan('user:super-admin', ['email' => 'owner@example.com'])
            ->expectsOutput('Owner (owner@example.com) is now a super admin.')->assertExitCode(0);
        $this->assertSame('super_admin', $u->fresh()->role);
        $this->artisan('user:super-admin', ['email' => 'nobody@example.com'])->assertExitCode(1);
    }
}
