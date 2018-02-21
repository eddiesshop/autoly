<?php

use Illuminate\Database\Seeder;

use App\Models\Status;

class StatusTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        //
        Status::firstOrCreate(['service' => 'Jira', 'name' => 'Progress Done']); //For when the dev first finishes the item they've been working on
        Status::firstOrCreate(['service' => 'Jira', 'name' => 'Ready For Testing']); //Whenever Start Testing is set. Either after progress done, or biz review completed
        Status::firstOrCreate(['service' => 'Jira', 'name' => 'Failed Testing']);
        Status::firstOrCreate(['service' => 'Jira', 'name' => 'Passed Testing']);
        Status::firstOrCreate(['service' => 'Jira', 'name' => 'Ready For Review']);//Can be used as a UAT phase.

        Status::firstOrCreate(['service' => 'GitHub', 'name' => 'Pull Request Created']);
        Status::firstOrCreate(['service' => 'GitHub', 'name' => 'Pull Request Merged']);
        Status::firstOrCreate(['service' => 'GitHub', 'name' => 'Performed Merge']);
        Status::firstOrCreate(['service' => 'GitHub', 'name' => 'Branch Created']);
        Status::firstOrCreate(['service' => 'GitHub', 'name' => 'Branch Deleted']);

        Status::firstOrCreate(['service' => 'Autoly', 'name' => 'GitHub Branch Associated to Jira Issue']);
    }
}
