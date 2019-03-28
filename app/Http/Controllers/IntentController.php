<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

use Twilio\Rest\Client;
use Twilio\Twiml;

use Illuminate\Support\Facades\Cache;

use Illuminate\Support\Facades\Log;

class IntentController extends Controller
{
    public function processIntent(Request $request)
    {
    	// If it's a `LaunchRequest` set the name to `LaunchRequest` else, pull the intent name from the request data
    	$intent_name = $request->input('request.type') === 'LaunchRequest' ? 'LaunchRequest' : $request->input('request.intent.name');

    	switch($intent_name)
    	{
    		case 'LaunchRequest':
    			// Saying "Alexa, open twilio assistant" will trigger this
		    	return response()->json([
		    		'version' => '1.0',
		    		'response' => [
			    		'outputSpeech' => [
			    			'type' => 'PlainText',
			    			'text' => 'Welcome to the Twilio Alexa assistant.',
			    		],
			    		// We want to start a session so our intents will work
			    		'shouldEndSession' => false,
			    	],
		    	]);
    		break;
    		case 'SelectIntent':
    			// we pull the phone number from the slot alexa sends us
    			$phone_number = $request->input('request.intent.slots.PHONE.value');

    			// return the phone number in the session data & ask what message to send, this will trigger the MessageIntent
		    	return response()->json([
		    		'version' => '1.0',
		    		'response' => [
			    		'outputSpeech' => [
			    			'type' => 'PlainText',
			    			'text' => 'What message would you like to send?',
			    		],
			    		'shouldEndSession' => false,
			    	],
			    	// Alexa stores session data and sends it with each request, typical sessions will not work
		    		'sessionAttributes' => [
		    			'phoneNumber' => $phone_number,
		    		],
		    	]);
    		break;
    		case 'MessageIntent':
    			// we grab the auth info from the environment file
    			$account_sid = env('TWILIO_ACCOUNT_SID');
    			$auth_token = env('TWILIO_AUTH_TOKEN');
    			$from_number = env('TWILIO_NUMBER');

    			// we grab to `To` number from the session data Alexa sends us
    			$to_number = $request->input('session.attributes.phoneNumber');

    			// message
    			$message = $request->input('request.intent.slots.MESSAGE.value');

    			// Create the Twilio call and route it to our callback
	   			$client = new Client($account_sid, $auth_token);
				$call = $client->account->calls->create(  
				    $to_number,
				    $from_number,
				    [
				    	'url' => route('twilio.callback'),
				    ]
				);

				// now cache the call sid and message
				Cache::put($call->sid, $message, 5);

				// Let the user know the call was successful
		    	return response()->json([
		    		'version' => '1.0',
		    		'response' => [
			    		'outputSpeech' => [
			    			'type' => 'PlainText',
			    			'text' => 'Sending ' . $message . ' to ' . $to_number,
			    		],
			    		'shouldEndSession' => true,
			    	],
		    	]);
			break;
    	}
    }

    public function processCall(Request $request)
    {
    	// get twilio call sid
    	$id = $_REQUEST['CallSid'];

    	// fetch message from cache
    	$message = Cache::get($id);

    	// generate twiml with response
    	$twiml = new Twiml();
    	$twiml->say($message, [
    		'voice' => 'alice',
    	]);

    	// return twiml
	    $response = Response::make($twiml, 200);
	    $response->header('Content-Type', 'text/xml');
	    return $response;
    }
}
