<?php

namespace Restruct\Silverstripe\SimpleCalendar {

    use Page;
    use Restruct\SimpleCalendar\Event;
    use Restruct\SimpleCalendar\GridFieldToggleShowPastButton;
    use Restruct\SimpleCalendar\ICSFeed;
    use SilverStripe\Forms\DropdownField;
    use SilverStripe\Forms\GridField\GridField;
    use SilverStripe\Forms\GridField\GridFieldConfig_RecordEditor;
    use SilverStripe\ORM\ArrayList;
    use UncleCheese\DisplayLogic\Forms\Wrapper;

    class SimpleCalendarPage extends Page
    {

        private static $table_name = 'SimpleCalendarPage';

        private static $singular_name = 'Calendar';
        private static $plural_name = 'Calendars';
        private static $description = 'Create a calendar page';

        private static $icon = 'simple-calendar/images/calendar.gif';

        private static $db = [
            'ShowEventsIn' => 'Enum("Future, Past, Both","Future")',
        ];

        private static $has_one = [
            'ShowEventsFromCalendar' => SimpleCalendarPage::class,
        ];

        private static $has_many = [
            'Events'            => Event::class,
            'ExternalCalendars' => ICSFeed::class,
        ];

        public function getCMSFields()
        {
            $fields = parent::getCMSFields();

            $fields->dataFieldByName('Content')->setRows(20)->addExtraClass('margin-left');

            $fields->addFieldsToTab('Root.Calendar', [
                DropdownField::create('ShowEventsFromCalendarID',
                    'Show calendar items from another calendar',
                    SimpleCalendarPage::get()->exclude('ID', $this->ID)->map('ID', 'BreadCrumbPath')->toArray()
                )->setHasEmptyDefault(true),
                DropdownField::create('ShowEventsIn',
                    _t('SIMPLECALENDAR.ShowEventsInFutureOrPast', 'Show events in'), [
                        'Future' => _t('SIMPLECALENDAR.TheFuture', 'The Future'),
                        'Past'   => _t('SIMPLECALENDAR.ThePast', 'The Past'),
                        'Both'   => _t('SIMPLECALENDAR.Both', 'Both'),
                    ]),
            ]);

            $fields->addFieldToTab('Root.Calendar',
                Wrapper::create(
                    new GridField(
                        'Events',
                        'Manage events',
                        $this->Events(),
                        GridFieldConfig_RecordEditor::create()
                            ->addComponent(new GridFieldCopyButton(), 'GridFieldEditButton')
//							->addComponent(new SC_GFToggleShowPastButton('buttons-before-right'))
                            ->addComponent(new GridFieldToggleShowPastButton('buttons-before-left'))
                    )
                )->displayUnless('ShowEventsFromCalendarID')->isGreaterThan(0)->end()
            );
            $fields->addFieldToTab('Root.CalendarFeeds',
                Wrapper::create(
                    new GridField(
                        'ExternalCalendars',
                        'External Calendar feeds (.ics)',
                        $this->ExternalCalendars(),
                        $gconf = GridFieldConfig_RecordEditor::create()
                    )
                )
            );

            return $fields;
        }

        // limit to future events.
        public function updateGetItems($items)
        {

            if ( $this->ShowEventsFromCalendarID ) {
                $items = $this->ShowEventsFromCalendar()->Events();
            }

            // from here on, $items becomes an ArrayList instead of a DataList
            $items = new ArrayList($items->toArray());
            foreach ( $this->ExternalCalendars() as $icsfeed ) {
                // merge-in the (virtual) events from .ics feeds
                $items->merge($icsfeed->GetFeedEvents());
            }
            // Because of switching to ArrayList, we need to filterByCallback in order to
            // be able to use the SearchFilter modifiers (they only work on DataList()
            switch ( $this->ShowEventsIn ) {
                case 'Past':
//				return $items->filter('Date:LessThanOrEqual',date("Y-m-d"))->sort('Date DESC');
                    return $items->filterByCallback(function ($item, $list) {
                        return strtotime($item->Date) <= strtotime(date("Y-m-d"));
                    })->sort('Date DESC');
                case 'Both':
                    return $items->sort('Date ASC');
                default:
//				return $items->filter('Date:GreaterThanOrEqual',date("Y-m-d"))->sort('Date ASC');
                    return $items->filterByCallback(function ($item, $list) {
                        return strtotime($item->Date) >= strtotime(date("Y-m-d"));
                    })->sort('Date ASC');
            }

        }

    }
}
