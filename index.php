<?php

/**
 * This sample app is provided to kickstart your experience using Facebook's
 * resources for developers.  This sample app provides examples of several
 * key concepts, including authentication, the Graph API, and FQL (Facebook
 * Query Language). Please visit the docs at 'developers.facebook.com/docs'
 * to learn more about the resources available to you
 */

// Provides access to app specific values such as your app id and app secret.
// Defined in 'AppInfo.php'
require_once('AppInfo.php');

// Enforce https on production
if (substr(AppInfo::getUrl(), 0, 8) != 'https://' && $_SERVER['REMOTE_ADDR'] != '127.0.0.1') {
  header('Location: https://'. $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
  exit();
}

// This provides access to helper functions defined in 'utils.php'
require_once('utils.php');


/*****************************************************************************
 *
 * The content below provides examples of how to fetch Facebook data using the
 * Graph API and FQL.  It uses the helper functions defined in 'utils.php' to
 * do so.  You should change this section so that it prepares all of the
 * information that you want to display to the user.
 *
 ****************************************************************************/

require_once('sdk/src/facebook.php');

$facebook = new Facebook(array(
  'appId'  => AppInfo::appID(),
  'secret' => AppInfo::appSecret(),
  'sharedSession' => true,
  'trustForwarded' => true,
));

$user_id = $facebook->getUser();
if ($user_id) {
  try {
    // Fetch the viewer's basic information
    $basic = $facebook->api('/me');
  } catch (FacebookApiException $e) {
    // If the call fails we check if we still have a user. The user will be
    // cleared if the error is because of an invalid accesstoken
    if (!$facebook->getUser()) {
      header('Location: '. AppInfo::getUrl($_SERVER['REQUEST_URI']));
      exit();
    }
  }

  $my_id = idx($basic, 'id');
  

  // This fetches 4 of your friends.
  $allFriends = idx($facebook->api('/me/friends'), 'data', array());
  $friendlists = idx($facebook->api('/me/friendlists'), 'data', array());
  
  $allEvents = idx($facebook->api('/me/events'), 'data', array());
  
  foreach ($allEvents as $events) {
	$eventID = idx($events, 'id');
	$individualEvent = idx($facebook->api('/' . $eventID . '/attending'), 'data', array());
	$countEvent = count($individualEvent);
  } 
  
  $friend_id = 0;
    foreach ($friendlists as $friendk) {
              // Extract the pieces of info we need from the requests above
              $id = idx($friendk, 'id');
              $name = idx($friendk, 'name');
			  
			  if ($name == "Close Friends") {
				  $friend_id = $id;
				}
	}			
					
  $friends = idx($facebook->api('/' . $friend_id . '/members'), 'data', array());
  // I added this to get the ids of close friends. $friends is an array of array of id..lol
  foreach($friends as $frd) {
	  $id = idx($frd, 'id');
	  $close_friends[] = $id;
  }	 

	// This returns the events you are attending
	$events = idx($facebook->api('/me/events?type=attending'), 'data', array());

	// Get the input from user which events to choose
	// for testing, right now it uses the first event
	$picked_event = reset($events);
  if ($picked_event != NULL) {
  	$picked_event_id = idx($picked_event, 'id'); /* handle null */
	$attending_people_for_picked_event = idx($facebook->api('/' . $picked_event_id . '/attending'), 'data', array());

	foreach($attending_people_for_picked_event as $person) {
	  $id = idx($person, 'id');
	  $attending_people_ids_for_picked_event[] = $id;
	}	 

	// Get friends who are attending this particular event
	$friends_attending_event = $facebook->api(array(
		'method' => 'fql.query',
		'query' => 'select uid, rsvp_status from event_member where uid IN (SELECT uid2 FROM friend WHERE uid1=me()) AND eid=' . $picked_event_id . ' and rsvp_status="attending";'
	));
  }

	// Gets the past max 5 events you attended, number of events is easily customizable by changing the number '5'
	$past_events = idx($facebook->api('/me/events/attending?since=0&until=yesterday&limit=5'), 'data', array());

	$attendees = array();
	foreach( $past_events as $event ) {
		$event_id = idx($event, 'id');
		$attendees = array_merge( $attendees, idx($facebook->api('/' . $event_id . '/attending'), 'data', array()) );
	}

	// Hash map of attendees. The second value indicates the number of attendings of the attendee in the past 5 events
	$hash_map_of_attendees = array();
	foreach( $attendees as $attendee ) {
		$attendee_id = idx($attendee, 'id');
		$att = idx($hash_map_of_attendees, $attendee_id);
		if ( is_null($att) )
		{
			$hash_map_of_attendees[$attendee_id] = 1;
		}
		else
		{
			$hash_map_of_attendees[$attendee_id] += 1;
		}
	}

	// Extracts people who have attended more than or equal to two events
	foreach( $attendees as $attendee ) {
		$attendee_id = idx($attendee, 'id');
		if ( $hash_map_of_attendees[$attendee_id] >= 3 )
		{
			$potential_friends[$attendee_id] = $hash_map_of_attendees[$attendee_id];
		}
	}

	// Remove close_friends from the list of potential friends, to get ids of the potential friends, use array_keys()
	$potential_friends_ids = array_keys($potential_friends);
	foreach( $potential_friends_ids as $pfi ) {
		if (!in_array($pfi, $close_friends) && $pfi != $my_id) {
			$potential_friends_who_are_not_friends_already[$pfi] = $potential_friends[$pfi];
		}
	}
	if ( is_null($potential_friends_who_are_not_friends_already) )
	{
		$potential_friends_ids_who_are_not_friends_already = array();
	}
	else
	{
		$potential_friends_ids_who_are_not_friends_already = array_keys($potential_friends_who_are_not_friends_already);
	}


  // Here is an example of a FQL call that fetches all of your friends that are
  // using this app
  $app_using_friends = $facebook->api(array(
    'method' => 'fql.query',
    'query' => 'SELECT uid, name FROM user WHERE uid IN(SELECT uid2 FROM friend WHERE uid1 = me()) AND is_app_user = 1'
));
}

// Fetch the basic info of the app that they are using
$app_info = $facebook->api('/'. AppInfo::appID());

$app_name = idx($app_info, 'name', '');

?>
<!DOCTYPE html>
<html xmlns:fb="http://ogp.me/ns/fb#" lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=2.0, user-scalable=yes" />

    <title><?php echo he($app_name); ?></title>
    <link rel="stylesheet" href="stylesheets/screen.css" media="Screen" type="text/css" />
    <link rel="stylesheet" href="stylesheets/my.css" media="Screen" type="text/css" />
    <link rel="stylesheet" href="stylesheets/mobile.css" media="handheld, only screen and (max-width: 480px), only screen and (max-device-width: 480px)" type="text/css" />

    <!--[if IEMobile]>
    <![endif]-->

	<!-- These are Open Graph tags.  They add meta 
data to your  -->
    <!-- site that facebook uses when your content is shared     -->
    <!-- over facebook.  You should fill these tags in with      -->
    <!-- your data.  To learn more about Open Graph, visit       -->
    <!-- 'https://developers.facebook.com/docs/opengraph/'       -->
    <meta property="og:title" content="<?php echo he($app_name); ?>" />
    <meta property="og:type" content="website" />
    <meta property="og:url" content="<?php echo AppInfo::getUrl(); ?>" />
    <meta property="og:image" content="<?php echo AppInfo::getUrl('/logo.png'); ?>" />
    <meta property="og:site_name" content="<?php echo he($app_name); ?>" />
    <meta property="og:description" content="My first app" />
    <meta property="fb:app_id" content="<?php echo AppInfo::appID(); ?>" />

    <script type="text/javascript" src="/javascript/jquery-1.7.1.min.js"></script>
    <link rel="stylesheet" href="http://code.jquery.com/ui/1.10.2/themes/smoothness/jquery-ui.css" />
    <script src="http://code.jquery.com/jquery-1.9.1.js"></script>
    <script src="http://code.jquery.com/ui/1.10.2/jquery-ui.js"></script>

    <script type="text/javascript">
      function logResponse(response) {
        if (console && console.log) {
          console.log('The response was', response);
        }
      }

      $(function(){
        // Set up so we handle click on the buttons
        $('#postToWall').click(function() {
          FB.ui(
            {
              method : 'feed',
              link   : $(this).attr('data-url')
            },
            function (response) {
              // If response is null the user canceled the dialog
              if (response != null) {
                logResponse(response);
              }
            }
          );
        });

        $('#sendToFriends').click(function() {
          FB.ui(
            {
              method : 'send',
              link   : $(this).attr('data-url')
            },
            function (response) {
              // If response is null the user canceled the dialog
              if (response != null) {
                logResponse(response);
              }
			}
          );
        });

        $('#sendRequest').click(function() {
          FB.ui(
            {
              method  : 'apprequests',
              message : $(this).attr('data-message')
            },
            function (response) {
              // If response is null the user canceled the dialog
              if (response != null) {
                logResponse(response);
              }
            }
          );
        });
      });
    </script>

      <style>
      #feedback { font-size: 1.4em; }
      #selectable .ui-selecting { background: #FECA40; }
      #selectable .ui-selected { background: #F39814; color: white; }
      #selectable { list-style-type: none; margin: 0; padding: 0; width: 60%; }
      #selectable li { margin: 3px; padding: 0.4em; font-size: 1.4em; height: 18px; }
      </style>
      <script>
      $(function() {
        $( "#selectable" ).selectable();
      });
      </script>

    <!--[if IE]>
      <script type="text/javascript">
        var tags = ['header', 'section'];
        while(tags.length)
          document.createElement(tags.pop());
      </script>
    <![endif]-->
  </head>
  <body>
    <div id="fb-root"></div>
    <script type="text/javascript">
      window.fbAsyncInit = function() {
        FB.init({
          appId      : '<?php echo AppInfo::appID(); ?>', // App ID
          channelUrl : '//<?php echo $_SERVER["HTTP_HOST"]; ?>/channel.html', // Channel File
          status     : true, // check login status
          cookie     : true, // enable cookies to allow the server to access the session
          xfbml      : true // parse XFBML
        });

        // Listen to the auth.login which will be called when the user logs in
        // using the Login button
        FB.Event.subscribe('auth.login', function(response) {
          // We want to reload the page now so PHP can read the cookie that the
          // Javascript SDK sat. But we don't want to use
          // window.location.reload() because if this is in a canvas there was a
          // post made to this page and a reload will trigger a message to the
          // user asking if they want to send data again.
          window.location = window.location;
        });

        FB.Canvas.setAutoGrow();
      };

      // Load the SDK Asynchronously
      (function(d, s, id) {
        var js, fjs = d.getElementsByTagName(s)[0];
        if (d.getElementById(id)) return;
        js = d.createElement(s); js.id = id;
        js.src = "//connect.facebook.net/en_US/all.js";
        fjs.parentNode.insertBefore(js, fjs);
      }(document, 'script', 'facebook-jssdk'));
    </script>

      <?php if (isset($basic)) { ?>
        </div><p>Signed in!</p></div>
      <?php } else { ?>
      <div>
        <p>Welcome to BubbleCrew, where we show you where your social bubbles have been and will be.</p>
        <p>You can get started by logging in below.</p>
        <div class="fb-login-button" data-scope="user_likes,user_photos,user_events,read_friendlists"></div>
      </div>
      <?php } ?>

    <div id="wrap">

      <!-- FRIENDS -->
      <div id="friendcolumn">

        <div class="list">
          <h3>Your Close Friends</h3>
          <ul class="friends">
            <?php
              foreach ($friends as $friend) {
                // Extract the pieces of info we need from the requests above
                $id = idx($friend, 'id');
                $name = idx($friend, 'name');
            ?>
            <li>
              <a href="https://www.facebook.com/<?php echo he($id); ?>" target="_top">
                <img src="https://graph.facebook.com/<?php echo he($id) ?>/picture?type=square" alt="<?php echo he($name); ?>">
                <?php echo he($name); ?>
              </a>
            </li>
            <?php
              }
            ?>
          </ul>
        </div>

      </div>

      <!-- EVENTS -->
      <div id="eventcolumn">
		<h3>People you should meet more!</h3>
			<?php
				if ( !is_null($potential_friends_who_are_not_friends_already) ) {
					foreach ($potential_friends_who_are_not_friends_already as $key => $value) {
						$id = $key;
							?>
							 
							<a href="https://www.facebook.com/<?php echo he($id); ?>" target="_top">
							<img src="https://graph.facebook.com/<?php echo he($id) ?>/picture?type=square" alt="<?php echo he($name); ?>">
							</a>
							<?php 
					}
				}
			?>
        <p>Events will be here!</p>
	        <ul class="events">
<?php
if (!is_null($events)) {
            foreach ($events as $event) {
              // Extract the pieces of info we need from the requests above
				$id = idx($event, 'id');
				$name = idx($event, 'name');
				$attending_people_for_event = idx($facebook->api('/' . $id . '/attending?limit=49'), 'data', array());
				$allAttendees = idx($facebook->api('/' . $id . '/attending'), 'data', array());

				$_attendingMale = 0;
				$_attendingFemale = 0;
				
				foreach($allAttendees as $person) {

					$ida = idx($person, 'id');
					$allAttendeesByID[] = $ida;
					print_r($allAttendeesBYID);
				}
			
				
				foreach ($attending_people_for_event as $friendse) {
					$fid = idx($friendse, 'id');
					$gender = idx($facebook->api('/' . $fid), 'gender');
					if ($gender == "male") {
						$_attendingMale = $_attendingMale + 1;
					}
					if ($gender == "female") {
						$_attendingFemale = $_attendingFemale + 1;
					}
				}
          ?>
          <li>
			<a href="https://www.facebook.com/<?php echo he($id); ?>" target="_top">
			<img src="https://graph.facebook.com/<?php echo he($id) ?>/picture?type=square" alt="<?php echo he($name); ?>">
              <?php 
				echo he($name); 
				?> </a>
      <div id="eventdetails">
        <?php
				?>
				<?php
			  ?>
				<?php
				echo he('%♂ = ' . round (100 * ($_attendingMale / ($_attendingMale + $_attendingFemale))));
				?>
				<?php
				echo he(' %♀ = ' . round (100 * ($_attendingFemale / ($_attendingMale + $_attendingFemale))));
				?>
				<?php
				?>
			<br>

            
          </li>
          <?php
			}
}
          ?>
        </ul>
      </div>
	  

    </div>

    <div id="fb-root"></div>
  <script>(function(d, s, id) {
    var js, fjs = d.getElementsByTagName(s)[0];
    if (d.getElementById(id)) return;
    js = d.createElement(s); js.id = id;
    js.src = "//connect.facebook.net/en_US/all.js#xfbml=1&appId=512435695470615";
    fjs.parentNode.insertBefore(js, fjs);
  }(document, 'script', 'facebook-jssdk'));</script>

  </body>
</html>

