<?php

namespace Restruct\Silverstripe\SimpleCalendar {

    use SilverStripe\Control\Cookie;
    use SilverStripe\Forms\GridField\GridField;
    use SilverStripe\Forms\GridField\GridField_ActionProvider;
    use SilverStripe\Forms\GridField\GridField_DataManipulator;
    use SilverStripe\Forms\GridField\GridField_FormAction;
    use SilverStripe\Forms\GridField\GridField_HTMLProvider;
    use SilverStripe\ORM\SS_List;

    /**
     * Adds an "Print" button to the bottom or top of a GridField.
     *
     * @package    forms
     * @subpackage fields-gridfield
     */
    class GridFieldToggleShowPastButton implements GridField_HTMLProvider, GridField_ActionProvider, GridField_DataManipulator
    {

        /**
         * Fragment to write the button to.
         *
         * @var string
         */
        protected $targetFragment;

        /**
         * Date field to filter on.
         *
         * @var string
         */
        protected $dateField;

        /**
         * @param string $targetFragment The HTML fragment to write the button into
         * @param array  $printColumns   The columns to include in the print view
         */
        public function __construct($targetFragment = "after", $dateField = "Date")
        {
            $this->targetFragment = $targetFragment;
            $this->dateField = $dateField;
        }

        /**
         * Place the print button in a <p> tag below the field
         *
         * @param GridField
         *
         * @return array
         */
        public function getHTMLFragments($grid)
        {

            if ( $this->getState($grid)->showPast ) {
                $btntxt = _t('SCal.HidePastEvents', 'Hide past events');
            } else {
                $btntxt = _t('SCal.ShowPastEvents', 'Show past events');
            }
            $button = new GridField_FormAction(
                $grid,
                'toggleshowpast',
                $btntxt,
                'toggleshowpast',
                null
            );

            $button->setAttribute('data-icon', 'arrow-circle-double');
            if ( strpos($this->targetFragment, 'after') !== false ) {
                $button->setAttribute('style', 'margin-top: 12px;'); // @TODO make proper CSS
            }
            if ( strpos($this->targetFragment, 'before') !== false ) {
                $button->setAttribute('style', 'margin-top: 1.5px;');
            }

//		$button->addExtraClass('gridfield-button-print');

            return [
                $this->targetFragment => '<p class="grid-print-button">' . $button->Field() . '</p>',
            ];
        }

        /**
         * toggleshowpast is an action button.
         *
         * @param GridField
         *
         * @return array
         */
        public function getActions($gridField)
        {
            return [ 'toggleshowpast' ];
        }

        /**
         * Handle the print action.
         *
         * @param GridField
         * @param string
         * @param array
         * @param array
         */
        public function handleAction(GridField $gridField, $actionName, $arguments, $data)
        {
            if ( $actionName === 'toggleshowpast' ) {
                return $this->handleToggleShowPast($gridField);
            }
        }

        /**
         * Print is accessible via the url
         *
         * @param GridField
         *
         * @return array
         */
        public function getURLHandlers($gridField)
        {
            return [
                'toggleshowpast' => 'handleToggleShowPast',
            ];
        }

        /**
         * Handle the print, for both the action button and the URL
         */
        public function handleToggleShowPast(GridField $grid, $request = null)
        {
            $state = $this->getState($grid);
            $state->showPast = !$state->showPast;
            // make sticky for handled class

            Cookie::set('gridfield_show_past_for_' . $grid->getModelClass(), $state->showPast);

            return $grid->FieldHolder();
        }

        public function getManipulatedData(GridField $grid, SS_List $list)
        {
            $state = $this->getState($grid);
            $showPast = $state->showPast;

            if ( $showPast ) {
                return $list;
            }

            return $list->filter($this->dateField . ':GreaterThanOrEqual', date("Y-m-d"));
        }

        /**
         * Retrieves/Sets up the state object used to store and retrieve the showPast status
         */
        protected function getState(GridField $grid)
        {
            $state = $grid->State->GridFieldToggleShowPast;
//		var_dump('gridfield_show_past_events'.$grid->getModelClass());
            // Force the state to the initial page if none is set
            if ( empty($state->showPast) ) {
                $state->showPast = 0;
                if ( Cookie::get('gridfield_show_past_for_' . $grid->getModelClass()) ) {
                    $state->showPast = 1;
                }
            }

            return $state;
        }

    }
}
