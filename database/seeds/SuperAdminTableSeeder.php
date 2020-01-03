<?php

use Illuminate\Database\Seeder;

class SuperAdminTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $id = \App\User::query()->insertGetId(
            [
                'name'              => 'Super Admin',
                'email'             => 'admin@management.com',
                'email_verified_at' => '',
                'password'          => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', // password
                'remember_token'    => Str::random(10),
            ]
        );
        Bouncer::allow(\App\User::find($id))->everything();
    }
}
