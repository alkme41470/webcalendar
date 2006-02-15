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
 * The output of this page conforms to the hCalendar standard for events.
 * You can read more about hCalendar at:
 *  http://microformats.org/wiki/hcalendar
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
 *   - showTitle (boolean, set to 1 or 0) whether the page title is shown or not
 *   - upcoming_title: The page title to print.  There is a default but this overrides it.
 *     Of course it will only be printed if showTitle so indicates.
 *   - showMore (boolean, set to 1 or 0) whether "more" at the end is shown or not,
 *         with a link to your main calendar page
 *   - showTime ((boolean, set to 1 or 0) whether the event time should be shown
 *
 * if calling as an include file can pre-set these variables in your PHP file
 * before including upcoming.php (you can't use URL parameters when calling 
 * an include file).  Remember that after debugging you can use @include to suppress
 * PHP warnings.
 *     $numDays              default 30
 *     $cat_id               default ALL
 *     $username             default __public__
 *     $maxEvents            default 10   
 *     $showTasks (boolean)  default true
 *     $showTitle (boolean)  default true
 *     $upcoming_title       default "Upcoming Events"
 *     $showMore (boolean)   default true
 *     $showTime (boolean)   default false
 *
 * To do: Cache results, used cached results mostly, only update occasionally.  This
 * is pretty simple to do and greatly speeds up the include file if you have a large
 * calendar.
 *
 * Security:
 * TBD
 */

//only go through the requires & includes & function declarations once, 
// in case upcoming.php is included twice on one page
//this trick allows the upcoming events to be displayed twice on one page
//(perhaps with different parameters) without causing problems if 
if ( empty ($upcoming_initialized)) {
  $upcoming_initialized=true;
//The following lines allow this include file to be called from another directory
//it saves the current working directory (to be restored just before exiting)
//and then changes the working directory to the dir that this file is currently
//in.  That allows this file to load its includes normally even if called
//from some other directory.
$save_current_working_dir= getcwd();
chdir(dirname(__FILE__));
  
include_once 'includes/init.php';
//This must contain the file name that this file is saved under.  It is 
//used to determine whether the file is being run independently or
//as an include file.  Change as necessary!
//Note that if you use any other name than "upcoming.php" you must
//also change the corresponding line in includes/classes/WebCalendar.class, about 
//line 54, like this:
//    '/^(nulogin|login|freebusy|publish|register|rss|upcoming|upcoming-.*|week_ssi|minical|controlpanel)\.php$/' =>
//Using upcoming-.* allows you to use names like upcoming-1.php, upcoming-2.php etc. 
//if you want have different upcoming-*.php files with variants.

$name_of_this_file="/upcoming.php/";

//echo "$showTitle $showMore $maxEvents $numDays $cat_id<p>";

load_global_settings ();

$WebCalendar->setLanguage();

 // Print the details of an upcoming event
 // This function is here, inside the 'if' that runs only the first time this
 // file is included within an external document, so that the function isn't 
 // declared twice in case of this file being included twice or more within the same doc.
 function print_upcoming_event ( $e, $date ) {
  global $display_link, $link_target, $SERVER_URL, $charset, $display_tzid, $showTime;

  if ( $display_link && ! empty ( $SERVER_URL ) ) {
    $cal_type = ( $e->getCalType() == 'T' || $e->getCalType() == 'N' ) ?
      'task' : 'entry';
    print "<a title=\"" . 
      htmlspecialchars ( $e->getName() ) . "\" href=\"" . 
      $SERVER_URL . 'view_' . $cal_type . '.php?id=' . 
        $e->getID() . "&amp;date=$date\"";
      if ( ! empty ( $link_target ) ) {
      print " target=\"$link_target\"";
    }
    print ">";
  }
  print htmlspecialchars ( $e->getName() );
  if ( $display_link && ! empty ( $SERVER_URL ) ) {
    print "</a>";
  }
  
  if ( $showTime ) {  //show event time if requested (default=don't show)
    if ( $e->isAllDay() ) {
      print " (" . translate("All day event") . ")\n";
    } else if ( $e->getTime() != -1 ) {
      print " (" . display_time ( $e->getDateTime(), $display_tzid ) . ")\n";
    }
  }

  print "<br />\n";

 }  //end function

} //end condition initialization

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
//bhugh, 1/28/2006, if(empty and !== false constructions allow these vars to be passed 
//from another php program in case upcoming.php is called as an include file
//(you can't pass ?days=60 type parameters when you use include)
if (empty($numDays))  $numDays = 30;
$showTitle = ( ! empty ( $showTitle ) && $showTitle !== false ? true : false );
$showMore = ( ! empty ( $showMore ) && $showMore !== false ? true : false );
$showTime = ( ! empty ( $showTime ) && $showTime !== false ? true : false );

//sets the URL used in the (optional) page title and 
//(optional) "...more" tag at the end.  If you want them to 
//go to a different URL you can specify that here.
$title_more_url=$SERVER_URL;

//set default upcoming title but allow it to be overridden
if (empty($upcoming_title)) $upcoming_title= '<A href="'. 
   $title_more_url . '">Upcoming Events</A>';

//echo "$numDays $showTitle $maxEvents <p>";

// Max number of events (including tasks) to display
if (empty($maxEvents)) $maxEvents = 10;

// Should we include tasks?
// (Only relavant if tasks are enabled in system settings AND enabled for
// display in calendar view for this user.  So, this is really
// a way to disable tasks from showing up.  It will not display
// them if specified user has not enabled "Display tasks in Calendars"
// in their preferences.)
if ( empty ( $showTasks ) ) $showTasks = false;

// Login of calendar user to use
// '__public__' is the login name for the public user
if (empty($username)) $username = '__public__';

// Allow the URL to override the user setting such as
// "upcoming.php?user=craig"
$allow_user_override = true;

// Load layers
$load_layers = true;

// Load just a specified category (by its id)
// Leave blank to not filter on category (unless specified in URL)
// Can override in URL with "upcoming.php?cat_id=4"
if (empty($cat_id)) $cat_id = '';

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

$get_unapproved = ( ! empty ( $DISPLAY_UNAPPROVED ) && $DISPLAY_UNAPPROVED == 'Y' );

if ( $CATEGORIES_ENABLED == 'Y' ) {
  $x = getIntValue ( "cat_id", true );
  if ( ! empty ( $x ) ) {
    $cat_id = $x;
  }
}

  $x = getGetValue ( "upcoming_title", true );
  if ( ! empty ( $x ) ) {
    $upcoming_title = $x;
  }

  $x = getGetValue ( "showMore", true );
  if ( strlen(  $x ) > 0 ) {
    $showMore= $x;
  }

  $x = getGetValue ( "showTime", true );
  if ( strlen(  $x ) > 0 ) {
    $showTime= $x;
  }


  $x = getGetValue ( "showTitle", true );
  if ( strlen(  $x ) > 0 ) {
    $showTitle = $x;
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
  && $showTasks ) {
  /* Pre-load tasks for quicker access */
  $tasks = read_tasks ( $username, $date, $endDate, $cat_id );
}


//Determine if this script is being called directly, or via an include
if ( empty ( $PHP_SELF ) && ! empty ( $_SERVER ) &&
  ! empty ( $_SERVER['PHP_SELF'] ) ) {
  $PHP_SELF = $_SERVER['PHP_SELF'];
}
//If called directly print  header stuff
if ( ! empty ( $PHP_SELF ) && preg_match ( $name_of_this_file, $PHP_SELF ) ) { 
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
<?php } //end test for direct call

if ( ! empty ( $error ) ) {
  echo "<h2>" . translate ( "Error" ) .
    "</h2>\n" . $error;
  echo "\n<br /><br />\n</body></html>";

  //restore previous working directory before exit
  if (strlen($save_current_working_dir)) chdir($save_current_working_dir);

  exit;
}

if ($showTitle) echo '<H3 class=cal_upcoming_title>'. translate($upcoming_title) . '</H3>';
?>

<div class="cal_upcoming">
<?PHP
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
    print "<dt>" . date_to_str ( $d,  translate ( "__month__ __dd__" ), true, true ) . "</dt>\n<dd>";
    for ( $j = 0; $j < count ( $ev ) && $numEvents < $maxEvents; $j++ ) {
      print_upcoming_event ( $ev[$j], $d );
      $numEvents++;
    }
    print "</dd>\n";
  }
}

print "</dl>\n";

if ( $showMore ) echo '<center><I><a href="'. $title_more_url . '"> . . . ' . 
   translate ("more") . '</a></I></center>';
?>
</div>
<?PHP
if ( ! empty ( $PHP_SELF ) && preg_match ( $name_of_this_file, $PHP_SELF ) ) { 
  print "</body>\n</html>";
}

//restore previous working directory before exit
if (strlen($save_current_working_dir)) chdir($save_current_working_dir);


?>
