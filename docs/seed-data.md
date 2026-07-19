# Seed data — organizations & sources (owner-curated, 2026-07-19)

Harvested from enjoylakecity8.wordpress.com (resources + podcast pages) and lakecityba.org.
Use to seed the directory (admin → Organizations) and the fetch pipeline (admin → Sources)
once Plan 2 ships. Categories map to the app enum: community | services | business | government.

## Fetchable sources (for the `sources` registry)

| Name | Type | URL | Notes |
|---|---|---|---|
| Enjoy Lake City (blog) | rss | https://enjoylakecity8.wordpress.com/feed/ | WordPress; auto RSS |
| Lake City Business Alliance blog | rss | https://www.lakecityba.org/blog?format=rss | Squarespace RSS convention — verify at deploy |
| Seattle Public Library — Lake City events | ics/html | https://www.spl.org/hours-and-locations/lake-city-branch | check for calendar feed at deploy |

Facebook pages (e.g. facebook.com/lakecityba) are not fetchable (auth wall) — directory links only.

## Organizations (for the directory)

### Community
- **Lake City Business Alliance** — https://www.lakecityba.org/ — business support, advocacy, community building (also on Facebook: facebook.com/lakecityba). Squarespace site with Events/Blog pages.
- **Build Lake City Together** — newsletter via enjoylakecity8.wordpress.com/newsletter-information/ — community partnership org; co-produces the community podcast.
- **Enjoy Lake City** — https://enjoylakecity8.wordpress.com/ — community info hub, resources list, podcast.
- **Aorta Artist Productions** — 12700 Lake City Way (behind Thriftology) — Lakota-founded photo/video/immersive art studio; NATIVE VOICES + ARTWORK IS REALWORK! series. Contact: jermicidal138@gmail.com.
- **Lake City Seniors** — https://lakecityseniors.org/ — free senior lunches & activities at Lamb of God Church; meal delivery.
- **Coyote North** — http://www.coyotecentral.org/ — youth creative courses (currently hibernating — recheck before listing as active).

### Services
- **North Helpline (Lake City Food Bank)** — https://www.northhelpline.org/ — food bank, financial assistance, homelessness prevention.
- **God's Li'l Acre Community Ministry** — https://seattlemennonite.org/community-ministry/ — laundry, showers, hygiene, kitchen for unhoused neighbors; From the Heart PNW meals on weekends.
- **Journey Christian Church** — https://journeyseattle.org/ — free Thursday dinners.
- **Lamb of God Lutheran Church** — https://www.lambofgodseattle.org/ — Sunday breakfast & dinner.
- **Northgate Community Dinner** — https://www.communitydinners.com/northgate/ — community meals at Northgate CC.
- **Akin (North Seattle Family Resource Center)** — https://www.childrenshomesociety.org/northking — parent-child programs, referrals, benefits help.
- **NeighborCare Health Lake City** — https://neighborcare.org/clinics/neighborcare-health-lake-city — primary care, dental, behavioral health.
- **Seattle Indian Health Board — Lake City Clinic** — https://www.sihb.org/patient-and-visitor-information/hours-and-locations/ — medical/dental/behavioral health for Native communities.
- **Sound Health Lake City** — https://www.sound.health/blog/locations/sound-lake-city/ — mental health, substance use treatment, psychiatry.
- **Literacy Source** — https://www.literacysource.org/ — free adult ed, ESL, citizenship, GED.
- **WorkSource at North Seattle College** — https://northseattle.edu/ocee-employment-services/worksource — job search & training.

### Government
- **Lake City Library (SPL)** — https://www.spl.org/hours-and-locations/lake-city-branch — books, programs, computer access.
- **North Seattle Dental Clinic (King County)** — https://www.kingcounty.gov/depts/health/locations/north/dental-clinic.aspx — dental care for low-income residents.

## Follow-ups
- enjoy-lake-city.org/resources-2/ referenced as a business-resources page — harvest at deploy.
- Ask LCBA + Build Lake City Together for ICS calendar feeds (best-quality event source).
