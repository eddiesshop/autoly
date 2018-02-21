<?php

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $this->call(UsersTableSeeder::class);
        $this->call(UserServicesTableSeeder::class);
        $this->call(StatusTableSeeder::class);
        $this->call(DirectivesTableSeeder::class);
    }
}
