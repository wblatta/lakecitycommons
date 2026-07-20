<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\Source;
use Illuminate\Database\Seeder;

/**
 * Idempotent seed of the owner-curated launch data (docs/seed-data.md).
 * Safe to re-run: matches organizations by name and sources by url.
 */
class LakeCityProductionSeeder extends Seeder
{
    public function run(): void
    {
        $organizations = [
            // Community
            ['name' => 'Lake City Business Alliance', 'category' => 'community', 'website' => 'https://www.lakecityba.org/', 'description' => 'Supports a thriving, connected, and welcoming community through business support, advocacy, and community building. Also on Facebook at facebook.com/lakecityba.'],
            ['name' => 'Build Lake City Together', 'category' => 'community', 'website' => 'https://enjoylakecity8.wordpress.com/newsletter-information/', 'description' => 'Community partnership organization; co-produces the Lake City community podcast.'],
            ['name' => 'Enjoy Lake City', 'category' => 'community', 'website' => 'https://enjoylakecity8.wordpress.com/', 'description' => 'Community info hub with neighborhood resources, news, and the community podcast.'],
            ['name' => 'Aorta Artist Productions', 'category' => 'community', 'website' => null, 'address' => '12700 Lake City Way NE (behind Thriftology)', 'email' => 'jermicidal138@gmail.com', 'description' => 'Lakota-founded photography, video production, and immersive art studio. Produces NATIVE VOICES and ARTWORK IS REALWORK!'],
            ['name' => 'Lake City Seniors', 'category' => 'community', 'website' => 'https://lakecityseniors.org/', 'description' => 'Free in-person lunches and activities at Lamb of God Church, with meal deliveries available.'],
            // Services
            ['name' => 'North Helpline', 'category' => 'services', 'website' => 'https://www.northhelpline.org/', 'description' => 'Lake City food bank, financial assistance, homelessness prevention, and client support services.'],
            ['name' => "God's Li'l Acre Community Ministry", 'category' => 'services', 'website' => 'https://seattlemennonite.org/community-ministry/', 'description' => 'Free laundry, showers, hygiene, and kitchen access for unhoused neighbors; weekend hot lunches with From the Heart PNW.'],
            ['name' => 'Journey Christian Church', 'category' => 'services', 'website' => 'https://journeyseattle.org/', 'description' => 'Free community dinner on Thursday evenings.'],
            ['name' => 'Lamb of God Lutheran Church', 'category' => 'services', 'website' => 'https://www.lambofgodseattle.org/', 'description' => 'Complimentary breakfast and dinner on Sundays.'],
            ['name' => 'Northgate Community Dinner', 'category' => 'services', 'website' => 'https://www.communitydinners.com/northgate/', 'description' => 'Community meal service at Northgate Community Center.'],
            ['name' => 'Akin — North Seattle Family Resource Center', 'category' => 'services', 'website' => 'https://www.childrenshomesociety.org/northking', 'description' => 'Parent-child programs, referrals, and assistance with employment and benefits.'],
            ['name' => 'NeighborCare Health Lake City', 'category' => 'services', 'website' => 'https://neighborcare.org/clinics/neighborcare-health-lake-city', 'description' => 'Primary care, dental, behavioral health, and specialized services.'],
            ['name' => 'Seattle Indian Health Board — Lake City Clinic', 'category' => 'services', 'website' => 'https://www.sihb.org/patient-and-visitor-information/hours-and-locations/', 'description' => 'Medical, dental, and behavioral health services centering Native communities.'],
            ['name' => 'Sound Health Lake City', 'category' => 'services', 'website' => 'https://www.sound.health/blog/locations/sound-lake-city/', 'description' => 'Mental health counseling, substance use treatment, and psychiatry services.'],
            ['name' => 'Literacy Source', 'category' => 'services', 'website' => 'https://www.literacysource.org/', 'description' => 'Free in-person and virtual adult education: ESL, citizenship, and GED.'],
            ['name' => 'WorkSource at North Seattle College', 'category' => 'services', 'website' => 'https://northseattle.edu/ocee-employment-services/worksource', 'description' => 'Job search assistance, training, and employment services.'],
            // Government
            ['name' => 'Lake City Library (Seattle Public Library)', 'category' => 'government', 'website' => 'https://www.spl.org/hours-and-locations/lake-city-branch', 'description' => 'Books, e-books, movies, and music for all ages, plus computer access and educational programs.'],
            ['name' => 'North Seattle Dental Clinic (King County)', 'category' => 'government', 'website' => 'https://www.kingcounty.gov/depts/health/locations/north/dental-clinic.aspx', 'description' => 'Dental care for low-income and vulnerable populations.'],
        ];

        foreach ($organizations as $data) {
            Organization::firstOrCreate(['name' => $data['name']], $data + ['active' => true]);
        }

        $sources = [
            [
                'url' => 'https://enjoylakecity8.wordpress.com/feed/',
                'name' => 'Enjoy Lake City (blog)',
                'type' => 'rss',
                'organization' => 'Enjoy Lake City',
            ],
            [
                'url' => 'https://www.lakecityba.org/blog?format=rss',
                'name' => 'Lake City Business Alliance (blog)',
                'type' => 'rss',
                'organization' => 'Lake City Business Alliance',
            ],
        ];

        foreach ($sources as $data) {
            $org = Organization::where('name', $data['organization'])->first();
            Source::firstOrCreate(['url' => $data['url']], [
                'name' => $data['name'],
                'type' => $data['type'],
                'organization_id' => $org?->id,
                'active' => true,
            ]);
        }
    }
}
