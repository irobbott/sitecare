<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Organisation;
use App\Models\Website;
use App\Models\Ticket;
use App\Models\TicketCategory;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        foreach ([
            'Website unavailable', 'Broken feature', 'Content update', 'Design or layout issue',
            'Performance', 'Security concern', 'Domain or DNS', 'SSL certificate', 'Backup request',
            'Email or form issue', 'E-commerce issue', 'Other',
        ] as $order => $name) {
            TicketCategory::firstOrCreate(['name' => $name], ['sort_order' => $order]);
        }

        $organisation=Organisation::firstOrCreate(['slug'=>'northstar-studio'],['name'=>'Northstar Studio','contact_email'=>'hello@northstar.test','status'=>'active']);
        $admin=User::firstOrCreate(['email'=>'admin@sitecare.test'],['name'=>'Demo Administrator','password'=>'password','role'=>'admin','is_demo'=>true]);
        $technician=User::firstOrCreate(['email'=>'tech@sitecare.test'],['name'=>'Morgan Lee','password'=>'password','role'=>'technician','is_demo'=>true]);
        $client=User::firstOrCreate(['email'=>'client@sitecare.test'],['name'=>'Jamie Parker','password'=>'password','role'=>'client','organisation_id'=>$organisation->id,'is_demo'=>true]);
        $website=Website::firstOrCreate(['organisation_id'=>$organisation->id,'name'=>'Northstar Studio'],['url'=>'https://example.com','description'=>'A sample client website for the development environment.','status'=>'active','technician_id'=>$technician->id]);
        if(!Ticket::where('organisation_id',$organisation->id)->exists()) Ticket::create(['number'=>'SC-'.now()->format('Y').'-00421','organisation_id'=>$organisation->id,'website_id'=>$website->id,'reporter_id'=>$client->id,'assignee_id'=>$technician->id,'subject'=>'Contact form is not sending submissions','description'=>'The contact form needs an investigation.','category'=>'Email or form issue','priority'=>'high','status'=>'in_progress','response_due_at'=>now()->addHours(4)]);
    }
}
