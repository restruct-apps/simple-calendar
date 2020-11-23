<?php

namespace Restruct\SimpleCalendar {

    use Restruct\InfoField\InfoField;
    use Restruct\NamedLinkField\NamedLinkField;
    use Restruct\SimpleCalendar\Pages\SimpleCalendarPage;
    use Restruct\Traits\EnforceCMSPermission;
    use SilverStripe\Control\Director;
    use SilverStripe\ORM\DataObject;
    use SilverStripe\View\Parsers\URLSegmentFilter;

    class Event extends DataObject
    {

        use EnforceCMSPermission;

        /**
         * @var string
         */
        private static $table_name = 'Restruct_SimpleCalendar_Event';

        private static $singular_name = 'Calendar Event';
        private static $plural_name = 'Calendar Events';
        private static $description = 'Create a calendar event';

        private static $db = [
            'Title'        => 'Varchar(200)',
            'Date'         => 'Date',
            'Time'         => 'Time',
            'EndDate'      => 'Date',
            'EndTime'      => 'Time',
            'Location'     => 'Varchar(256)',
            'Description'  => 'Text',
            'MoreInfoLink' => NamedLinkField::class,
            'PageContent'  => 'HTMLText',
        ];

        private static $has_one = [
            'Calendar' => SimpleCalendarPage::class,
            'ICSFeed'  => ICSFeed::class, // Internet Schedule/Calendar file e.g. Google Cal
        ];

        private static $summary_fields = [
            'IsPast'             => 'Past',
            'Title'              => 'Title',
//		'Date.Nice' => 'Date and Time',
            'DateTimeSpan'       => 'Date',
//		'Link' => 'Link',
//		'DatesAndTimeframe' => 'Presentation String',
            'CategoriesAsString' => 'Categories',
        ];

        private static $default_sort = 'Date ASC';

        public function validate()
        {
            $result = parent::validate();

            if ( $this->EndDate &&
                $this->dbObject('Date')->format('U') > $this->dbObject('EndDate')->format('U') ) {
                $result->error('End date cannot be before start', "required");
                $ret = false;
            }

            return $result;
        }

        public function getCMSFields()
        {
            $fields = parent::getCMSFields();

//		$catfield = $fields->dataFieldByName('Categories');
            $fields->removeByName([ 'CalendarID' ]);
//		$fields->insertAfter($catfield, 'Title');

            // Show some instructions
            $fields->addFieldToTab('Root.EventPage',
                InfoField::create("CalendarInfo",
                    '&bull; If "Page Content" is filled out, the event will show a "Read more" link in the overview.<br />
					&bull; If a "More Info Link" is set, the event will link to another page for more information <strong>instead</strong>.'
                ));

//		$fields->push(InlineInfoField::create('PageContent', 
//			_t('SIMPLECAL.PageContentDescr', ''))
//		);

            $fields->addFieldToTab('Root.EventPage', $fields->dataFieldByName('MoreInfoLink'));

            $fields->addFieldToTab('Root.EventPage',
                $fields->dataFieldByName('PageContent')
                    ->addExtraClass('small margin-left')
//				->hideIf('Description')->isEmpty()->end();
//				->hideIf('MoreInfoLink[Title]')->isEmpty()->end();
//				->hideIf('MoreInfoLink #Form_ItemEditForm_MoreInfoLink-Title')->isEmpty()->end();
            );

            foreach ( [ 'Date', 'EndDate' ] as $fieldname ) {
                $fields->dataFieldByName($fieldname)
                    //			->getDateField()
                    ->setConfig('dateformat', 'dd-MM-yyyy')
                    ->setConfig('showcalendar', 1)
                    ->setDescription('')
                    ->addExtraClass('fourth-width')
                    ->setAttribute('placeholder', 'dd-mm-yyyy');
                //			->setAttribute('readonly', 'true'); //we only want input through the datepicker
            }
            foreach ( [ 'Time', 'EndTime' ] as $fieldname ) {
                $fields->dataFieldByName($fieldname)
                    //			->getTimeField()
                    ->setConfig('timeformat', 'HH:mm') //24h format
                    ->setDescription('')
                    ->addExtraClass('inline-block eighth-width')
                    ->setAttribute('placeholder', '00:00');
            }
            $fields->dataFieldByName('Time')->setDescription('Leave empty for all-day');
            $fields->dataFieldByName('EndTime')->displayIf("Time")->isNotEmpty();

            return $fields;
        }

        // Helpers

        public function IsPast()
        {
            return $this->dbObject('Date')->inPast() ? "Yes" : "";
        }

        public function CategoriesAsString()
        {
            return implode(', ', $this->relField('Categories')->column('Title'));
        }

        public function DateTimeSpan()
        {
            $spanstart = '';
            $spanend = '';
            if ( !$this->Date ) return;

            $start = $this->dbObject('Date');
            $starttime = $this->dbObject('Time');
            $end = $this->dbObject('EndDate');
            $endtime = $this->dbObject('EndTime');

            // all day, single day
            if ( !$this->Time && !$this->EndDate ) {
                return $start->Format('d M Y');
                // single day, with only starttime
            } elseif ( $this->Time && !$this->EndDate && !$this->EndTime ) {
                return $start->Format('d M Y, ') . $starttime->Format('H:i');
                // single day, with start & endtime
            } elseif ( $this->Time && !$this->EndDate && $this->EndTime ) {
                return $start->Format('d M Y, ') . $starttime->Format('H:i') . $endtime->Format('-H:i');
                // multi-day
            } elseif ( $this->EndDate ) {
                if ( $start->Format('M Y') == $end->Format('M Y') && !$this->Time && !$this->EndTime ) {
                    $spanstart = $start->Format('d');
                    $spanend = $end->Format('d M Y');
                } elseif ( $start->Format('Y') == $end->Format('Y') ) {
                    $spanstart = $start->Format('d M');
                    $spanend = $end->Format('d M Y');
                } else {
                    $spanstart = $start->Format('d M Y');
                    $spanend = $end->Format('d M Y');
                }
            }
            // add start & endtime if set
            if ( $this->Time ) {
                $spanstart .= $starttime->Format(', H:i');
            }
            if ( $this->EndTime ) {
                $spanend .= $endtime->Format(', H:i');
            }

            return $spanstart . ' – ' . $spanend;
        }


        public function AbsoluteLink()
        {
            return Director::absoluteURL($this->Link());
        }

        public function Link()
        {
            if ( !$this->PageContent ) return false;

//		$simpleCalController = SimpleCalendar::get()->first();
            $simpleCalController = $this->Calendar();
            if ( $simpleCalController ) {
                return $simpleCalController->Link() . "event/{$this->ID}/{$this->URLSegment()}";
            }

            return false;
        }

        public function URLSegment()
        {
            $filter = URLSegmentFilter::create();

            return $filter->filter($this->Title);
        }

    }
}