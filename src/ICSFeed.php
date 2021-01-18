<?php

namespace Restruct\SilverStripe\SimpleCalendar {

    use GuzzleHttp\Client;
    use Restruct\Traits\EnforceCMSPermission;
    use SilverStripe\ORM\ArrayList;
    use SilverStripe\ORM\DataObject;
    use ICal;
    use DateTime;
    use DateTimeZone;
    use DateInterval;

    class ICSFeed extends DataObject
    {

        use EnforceCMSPermission;

        private static $table_name = 'SimpleCalendar_ICSFeed';

        private static $db = [
            'Title' => 'Varchar(128)',
            'URL'   => 'Varchar(1024)',
        ];

        private static $has_one = [
            'Calendar' => SimpleCalendarPage::class,
        ];

        private static $has_many = [
            'FeedEvents' => Event::class,
        ];

        private  static $summary_fields = [
            'Title' => 'Title',
            'URL'   => 'URL',
        ];

        public function getCMSFields()
        {

            $fields = parent::getCMSFields();

            $fields->dataFieldByName('Categories')
                ->setTitle(_t('SIMPLECALENDAR.Apply', 'Adding feed to calendar'));

            $fields->dataFieldByName('Title')->setTitle(_t('SIMPLECALENDAR.TitleOfFeed', 'Title of feed'));

            $fields->dataFieldByName('URL')
                ->setTitle(_t('SIMPLECALENDAR.IcsFileUrl', '.ics file/URL'))
                ->setAttribute('placeholder', 'http://...');

            $fields->insertBefore(
                $fields->dataFieldByName('CalendarID')
                    ->setTitle(_t('SIMPLECALENDAR.AddingFeedToCal', 'Adding feed to calendar'))
                , 'Title');

            $fields->dataFieldByName('Categories')
                ->setTitle(_t('SIMPLECALENDAR.ApplyCategories', 'Apply categories'))
                ->setDescription(_t('SIMPLECALENDAR.ApplyCategoriesDesc',
                    'Selected categories will be applied to events loaded from this feed'));

            $this->extend('updateCMSFields', $fields);

            return $fields;

        }

        // Room foor (speed)improvement: deleting 180 events: 13 sec, writing 180 events: 23 sec
        // instead of writing all events to the database, just create a virtual list of them...
        public function GetFeedEvents()
        {
            // first, remove all existing events for this feed
            //ICSFeed
//		var_dump("Delete existing: ".microtime(true));
//		$this->FeedEvents()->removeAll();
//		SimpleCalendarEvent::get()->filter('ICSFeedID',$this->ID)->removeAll();
//		$query = new SQLQuery();
//		$query->setDelete(true);
//		$query->setFrom('SimpleCalendarEvent');
//		$query->setWhere('ICSFeedID='.Convert::raw2sql($this->ID));
////		$query->setWhere('CalendarID='.Convert::raw2sql($this->CalendarID));
//		$query->execute();
//		var_dump("Deleting done: ".microtime(true));

            if ( !$this->URL ) return;
//		$cachekey = md5($icsfeed->URL);
//		$cache = SS_Cache::factory('SimpleCalendarFeeds'); 
//		if (!($result = $cache->load($cachekey))) {
//			$result = ;
//			$cache->save($result, $cachekey);
//		}
//		var_dump("Retrieve ICS from cache or URL: ".microtime(true));
            $cache_dur = 10 * 60; // 10 minutes;
            //$service = new RestfulService($this->URL, $cache_dur);
            //$result = $service->request();

            $client = new Client();
            $result = $client->request('GET', $this->URL);

            $feed_content = $result->getBody();
//		var_dump("ICS retrieval done: ".microtime(true));
            /* ...
    BEGIN:VEVENT
    DTSTART:20160112T150000Z
    DTEND:20160112T160000Z
    DTSTAMP:20151231T071119Z
    UID:7qn7peu89aibof8v3e76vkbbf4@google.com
    CREATED:20151229T094804Z
    DESCRIPTION:Desc
    LAST-MODIFIED:20151229T094804Z
    LOCATION:
    SEQUENCE:0
    STATUS:CONFIRMED
    SUMMARY:Summary
    TRANSP:OPAQUE
    END:VEVENT
             */
            /*
            echo 'SUMMARY: ' . @$event['SUMMARY'] . "<br />\n";
            echo 'DTSTART: ' . $event['DTSTART'] . ' - UNIX-Time: ' . $ical->iCalDateToUnixTimestamp($event['DTSTART']);
            echo 'DTEND: ' . $event['DTEND'] . "<br />\n";
            echo 'DTSTAMP: ' . $event['DTSTAMP'] . "<br />\n";
            echo 'UID: ' . @$event['UID'] . "<br />\n";
            echo 'CREATED: ' . @$event['CREATED'] . "<br />\n";
            echo 'LAST-MODIFIED: ' . @$event['LAST-MODIFIED'] . "<br />\n";
            echo 'DESCRIPTION: ' . @$event['DESCRIPTION'] . "<br />\n";
            echo 'LOCATION: ' . @$event['LOCATION'] . "<br />\n";
            echo 'SEQUENCE: ' . @$event['SEQUENCE'] . "<br />\n";
            echo 'STATUS: ' . @$event['STATUS'] . "<br />\n";
            echo 'TRANSP: ' . @$event['TRANSP'] . "<br />\n";
            echo 'ORGANIZER: ' . @$event['ORGANIZER'] . "<br />\n";
            echo 'ATTENDEE(S): ' . @$event['ATTENDEE'] . "<br />\n";
             */
//		var_dump("Parsing ICS: ".microtime(true));
            $ical = new ICal(explode("\n", $feed_content));
//		var_dump("Parsing done: ".microtime(true));

//		var_dump("Writing {$ical->event_count} events: ".microtime(true));
            // Map VEVENT to SimpleCalendarEvent
            $feedEvents = new ArrayList();
            foreach ( $ical->events() as $event ) {
                $newevent = new Event();
//			if(strpos($event['SUMMARY'], 'Goflex')){
//				print_r($event);
//			}
//			print date('d M Y H:i:s',$ical->iCalDateToUnixTimestamp(@$event['DTSTART']))."<br />";
//			print date('d M Y H:i:s',  strtotime(@$event['DTSTART']))."<br />";

                // datetimes are parsed from Zulu to Zulu with offset, eg 20160112CET1500003600
                $dts = new DateTime(@$event[ 'DTSTART' ]);
                $dts->setTimezone(new DateTimeZone(date_default_timezone_get()));
                $newevent->Date = $dts->format('Y-m-d');
                $dte = new DateTime(@$event[ 'DTEND' ]);
                $dte->setTimezone(new DateTimeZone(date_default_timezone_get()));
                // account for parse error in library: adds one day for enddate of full day events
                if ( !strpos(@$event[ 'DTEND' ], 'T') || strpos(@$event[ 'DTEND' ], 'T000000') ) {
                    $oneDayInterval = new DateInterval('P1D');
                    //$oneDayInterval->invert = 1; //Make it negative
                    $dte->sub($oneDayInterval);
                }
                // only set enddate if different from startdate
                $newevent->EndDate = ( $dts->format('Y-m-d') != $dte->format('Y-m-d') ? $dte->format('Y-m-d') : null );
//			$newevent->EndDate = ($dts->format('Y-m-d')!=$dte->format('Y-m-d') ? $dte->format('Y-m-d') : null);
                // no time set = full day (ical creates crap like T000000 instead of null
                $newevent->Time = ( strpos(@$event[ 'DTSTART' ], 'T') && !strpos(@$event[ 'DTSTART' ], 'T000000') ?
                    $dts->format('H:i:s') : null );
                $newevent->EndTime = ( strpos(@$event[ 'DTEND' ], 'T') && !strpos(@$event[ 'DTEND' ], 'T000000') ?
                    $dte->format('H:i:s') : null );
                // continue with other fields
                $newevent->Title = @$event[ 'SUMMARY' ];
                $newevent->Description = @$event[ 'DESCRIPTION' ]; //.' STATUS: ' . @$event['STATUS'];
                $newevent->Location = @$event[ 'LOCATION' ];
                $newevent->ICSFeedID = $this->ID;
                $newevent->CalendarID = $this->CalendarID;

                // Apply categories
                foreach ( $this->Categories() as $cat ) {
                    $newevent->Categories()->add($cat);
                }
//			$newevent->write();

                $feedEvents->add($newevent);
            }

//		var_dump("Writing done: ".microtime(true));
            return $feedEvents;
        }


    }
}