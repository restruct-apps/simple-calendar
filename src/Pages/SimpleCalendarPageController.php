<?php

namespace Restruct\SimpleCalendar\Pages {

    use PageController;
    use Restruct\SimpleCalendar\Event;
    use SilverStripe\ErrorPage\ErrorPage;
    use SilverStripe\View\ArrayData;
    use SilverStripe\View\Parsers\ShortcodeParser;

    class SimpleCalendarPageController extends PageController
    {


        private static $allowed_actions = [
            'event',
        ];

        // view a file or video resource on a page (by id)
        public function event()
        {
            if ( $item = Event::get()->byID($this->request->param('ID')) ) {

                // if incorrect url, redirect to correct location (SEO)
                // + If MoreInfoLink.URL set, redirect to that instead.
                if ( $this->request->param('OtherID') !== $item->URLSegment() ) {
                    $this->redirect("event/{$item->ID}/{$item->URLSegment()}", 301);
                }
                if ( $item->dbObject('MoreInfoLink')->URL ) {
                    $this->redirect($item->dbObject("MoreInfoLink")->URL, 301);
                }

                // all OK, return item
                return $this->customise([
                    'Title'         => $item->Title,
                    'MenuTitle'     => $item->Title,
                    'Content'       => ShortcodeParser::get_active()->parse($item->PageContent),
                    'CalendarEvent' => $item,
                ])->renderWith([ 'CalendarItemPage', 'Page' ]);

            }

            return ErrorPage::response_for(404);
        }

    }
}