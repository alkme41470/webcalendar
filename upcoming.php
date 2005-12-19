<?php
/*
 * $Id$
 *
 * Description:
 * Show a list of upcoming events (and possibly tasks).
 *
 * This script is intended to be used outside of normal WebCalendar
 * use, typically as an iframe in another page.
 *
 * You must have public access enabled in System Settings to use this
 * page (unless you modify the $public_must_be_enabled setting below
 * in this file).
 *
 * Typically, this is how you would reference this page from another:
 *
 * <iframe height="250" width="300"
 *  scrolling="yes" src="upcoming.php"></iframe>
 *
 * By default (if you do not edit this file), events for the public
 * calendar will be loaded for either:
 *   - the next 30 days
 *   - the next 10 events
 *
 * Input parameters:
 * You can override settings by changing the URL parameters:
 *   - days: number of days ahead to look for events
 *   - cat_id: specify a category id to filter on
 *   - user: login name of calendar to display (instead of public
 *     user), if allowed by System Settings.  You must have the
 *     following System Settings configured for this:
 *       Allow viewing other user's calendars: Yes
 *       Public access can view others: Yes
 *   - tasks: specify a value of '1' to show just tasks (if permitted
 *       by system settings and config settings below).  This will
 *       show only tasks and not show any events.
 *
 * Security:
 * TBD
 */

require_once 'includes/classes/WebCalendar.class';
require_once 'includes/classes/Event.class';
require_once 'includes/classes/RptEvent.class';

$WebCalendar =& new WebCalendar ( __FILE__ );

include 'includes/config.php';
include 'includes/php-dbi.php';
include 'includes/functions.php';

$WebCalendar->initializeFirstPhase();

include "includes/$user_inc";
include 'includes/translate.php';

$WebCalendar->initializeSecondPhase();

load_global_settings ();

$WebCalendar->setLanguage();

/*
 *
 * Configurable settings for this file.  You may change the settings
 * below to change the default settings.
 * This settings will likely move into the System Settings in the
 * web admin interface in a future release.
 *
 */

// Set this to false if you still want to access this page even
// though you do not have public access enabled.
// Set this to true to require public access enabled for this page to
// function at all.
$public_must_be_enabled = false;

// Do we include a link to view the event?  If so, what target
// should we use.
$display_link = true;
$link_target = '_top';


// Default time window of events to load
// Can override with "upcoming.php?days=60"
$numDays = 30;

// Max number of events (including tasks) to display
$maxEvents = 10;

// Should we include tasks?
// (Only relavant if tasks are enabled in system settings AND enabled for
// display in calendar view for this user.  So, this is really
// a way to disable tasks from showing up.  It will not display
// them if specified user has not enabled "Display tasks in Calendars"
// in their preferences.)
$show_tasks = true;

// Login of calendar user to use
// '__public__' is the login name for the public user
$username = '__public__';

// Allow the URL to override the user setting such as
// "upcoming.php?user=craig"
$allow_user_override = true;

// Load layers
$load_layers = true;

// Load just a specified category (by its id)
// Leave blank to not filter on category (unless specified in URL)
// Can override in URL with "upcoming.php?cat_id=4"
$cat_id = '';

// Display timezone abbrev name
// 1 = Display all times as GMT wo/TZID
// 2 = Adjust times by user's GMT offset Show TZID 
// 3 = Display all times as GMT w/TZID
$display_tzid = 2;

// End configurable settings...

// Set for use elsewhere as a global
$login = $username;
// Load user preferences for DISPLAY_UNAPPROVED
load_user_preferences ();

if ( $public_must_be_enabled && $PUBLIC_ACCESS != 'Y' ) {
  $error = translate ( "You are not authorized" ) . ".";
}

if ( $allow_user_override ) {
  $u = getValue ( "user", "[A-Za-z0-9_\.=@,\-]+", true );
  if ( ! empty ( $u ) ) {
    $username = $u;
    $login = $u;
    $TIMEZONE = get_pref_setting ( $username, "TIMEZONE" );
    $DISPLAY_UNAPPROVED = get_pref_setting ( $username, "DISPLAY_UNAPPROVED" );
    $DISPLAY_TASKS_IN_GRID =
      get_pref_setting ( $username, "DISPLAY_TASKS_IN_GRID" );
    // We also set $login since some functions assume that it is set.
  }
}

$get_unapproved = ! empty ( $DISPLAY_UNAPPROVED ) && $DISPLAY_UNAPPROVED == 'Y';

$cat_id = '';
if ( $CATEGORIES_ENABLED == 'Y' ) {
  $x = getIntValue ( "cat_id", true );
  if ( ! empty ( $x ) ) {
    $cat_id = $x;
  }
}

if ( $load_layers ) {
  load_user_layers ( $username );
}

//load_user_categories ();

// Calculate date range
$date = getIntValue ( "date", true );
if ( empty ( $date ) || strlen ( $date ) != 8 ) {
  // If no date specified, start with today
  $date = date ( "Ymd" );
}
$thisyear = substr ( $date, 0, 4 );
$thismonth = substr ( $date, 4, 2 );
$thisday = substr ( $date, 6, 2 );

$startTime = mktime ( 0, 0, 0, $thismonth, $thisday, $thisyear );

$x = getIntValue ( "days", true );
if ( ! empty ( $x ) ) {
  $numDays = $x;
}
// Don't let a malicious user specify more than 365 days
if ( $numDays > 365 ) {
  $numDays = 365;
}
$endTime = mktime ( 0, 0, 0, $thismonth, $thisday + $numDays,
  $thisyear );
$endDate = date ( "Ymd", $endTime );

$tasks_only = getValue ( "tasks", "[01]", true );
$tasks_only = ( $tasks_only == '1' );

if ( $tasks_only ) {
  $repeated_events = $events = array ();
} else {

  /* Pre-Load the repeated events for quckier access */
  $repeated_events = read_repeated_events ( $username, $cat_id, $date );

  /* Pre-load the non-repeating events for quicker access */
  $events = read_events ( $username, $date, $endDate, $cat_id );
}

// Pre-load tasks for quicker access */
if ( ( empty ( $DISPLAY_TASKS_IN_GRID ) || $DISPLAY_TASKS_IN_GRID == 'Y' )
  && $show_tasks ) {
  /* Pre-load tasks for quicker access */
  $tasks = read_tasks ( $username, $date, $endDate, $cat_id );
}


// Print header without custom header and no style sheet
if ( ! empty ( $LANGUAGE ) ) {
  $charset = translate ( "charset" );
  $lang = languageToAbbrev ( ( $LANGUAGE == "Browser-defined" || 
    $LANGUAGE == "none" )? $lang : $LANGUAGE );
  if ( $charset != "charset" ) {
    echo "<?xml version=\"1.0\" encoding=\"$charset\"?>\n" .
      "<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Transitional//EN\" " .
      "\"DTD/xhtml1-transitional.dtd\">\n" .
      "<html xmlns=\"http://www.w3.org/1999/xhtml\" " .
      "xml:lang=\"$lang\" lang=\"$lang\">\n" .
      "<head>\n" .
      "<meta http-equiv=\"Content-Type\" content=\"text/html; " .
      "charset=$charset\" />\n";
  } else {
    echo "<?xml version=\"1.0\" encoding=\"iso-8859-1\"?>\n" .
      "<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Transitional//EN\" " .
      "\"DTD/xhtml1-transitional.dtd\">\n" .
      "<html xmlns=\"http://www.w3.org/1999/xhtml\" " .
      "xml:lang=\"en\" lang=\"en\">\n" .
      "<head>\n";
  }
} else {
  echo "<html>\n";
  $charset = "iso-8859-1";
}
echo "<title>".translate($APPLICATION_NAME)."</title>\n";
 
?>
<!-- This style sheet is here mostly to make it easier for others
     to customize the appearance of the page.
     In the not to distant future, the admin UI will allow configuration
     of the stylesheet elements on this page.
-->
<style type="text/css">
body {
  background-color: #fff;
}
dt {
  font-family: arial,helvetica;
  font-weight: bold;
  font-size: 12px;
  color: #000;
}
dd {
  font-family: arial,helvetica;
  color: #33a;
  font-size: 12px;
}
a {
  font-family: arial,helvetica;
  color: #33a;
}
a:hover {
  font-family: arial,helvetica;
  color: #fff;
  background-color: #33a;
}
</style>
</head>
<body>
<?php
if ( ! empty ( $error ) ) {
  echo "<h2>" . translate ( "Error" ) .
    "</h2>\n" . $error;
  echo "\n<br /><br />\n</body></html>";
  exit;
}
print "<dl>\n";

print "<!-- \nstartTime: $startTime\nendTime: $endTime\nstartDate: " .
  "$date\nnumDays: $numDays\nuser: $username\nevents: " . 
  count ( $events ) . "\nrepeated_events: " . 
  count ( $repeated_events ) . " -->\n";

$numEvents = 0;
for ( $i = $startTime; date ( "Ymd", $i ) <= date ( "Ymd", $endTime ) &&
  $numEvents < $maxEvents; $i += ( 24 * 3600 ) ) {
  $d = date ( "Ymd", $i );
  $entries = get_entries ( $username, $d, $get_unapproved );
  $rentries = get_repeating_entries ( $username, $d, $get_unapproved );
  $ev = combine_and_sort_events ( $entries, $rentries );

  $tentries = get_tasks ( $user, $d, $get_unapproved );
  $ev = combine_and_sort_events ( $ev, $tentries );

  print "<!-- $d " . count ( $ev ) . " -->\n";

  if ( count ( $ev ) > 0 ) {
    print "<!-- XXX -->\n";
    print "<dt>" . date_to_str ( $d ) . "</dt>\n<dd>";
    for ( $j = 0; $j < count ( $ev ) && $numEvents < $maxEvents; $j++ ) {
      print_upcoming_event ( $ev[$j] );
      $numEvents++;
    }
    print "</dd>\n";
  }
}

print "</dl>\n";

print "</body>\n</html>";


// Print the details of an upcoming event
function print_upcoming_event ( $e ) {
  global $display_link, $link_target, $SERVER_URL, $charset, $display_tzid;

  if ( $display_link && ! empty ( $SERVER_URL ) ) {
    $cal_type = ( $e->getCalType() == 'T' || $e->getCalType() == 'N' ) ?
      'task' : 'entry';
    print "<a title=\"" . 
      htmlspecialchars ( $e->getName() ) . "\" href=\"" . 
      $SERVER_URL . 'view_' . $cal_type . '.php?id=' . 
      $e->getID() . "&amp;date=" . 
      $e->getDate() . "\"";
    if ( ! empty ( $link_target ) ) {
      print " target=\"$link_target\"";
    }
    print ">";
  }
  print htmlspecialchars ( $e->getName() );
  if ( $display_link && ! empty ( $SERVER_URL ) ) {
    print "</a>";
  }
  if ( $e->isAllDay() ) {
    print " (" . translate("All day event") . ")\n";
  } else if ( $e->getTime() != -1 ) {
    print " (" . display_time ( $e->getDateTime(), $display_tzid ) . ")\n";
  }
  print "<br />\n";
}
?>
