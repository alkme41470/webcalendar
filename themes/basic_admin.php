<?php
/* Custom theme for use with WebCalendar.
 *
 * @author Ray Jones <rjones@umces.edu>
 * @copyright Craig Knudsen, <cknudsen@cknudsen.com>, http://k5n.us/webcalendar
 * @license https://gnu.org/licenses/old-licenses/gpl-2.0.html GNU GPL
 * @package WebCalendar
 */

// Define your stuff here...
// Any option in webcal_user_pref can be configured here.

// This theme sets the default System Settings for a few display options.
// This will only affect new users or users who have not set their own preferences.
$webcal_theme = [
  'DISPLAY_SM_MONTH'      => 'N',
  'DISPLAY_TASKS'         => 'N',
  'DISPLAY_TASKS_IN_GRID' => 'N',
  'DISPLAY_WEEKENDS'      => 'N'];

require_once 'theme_inc.php';

?>
