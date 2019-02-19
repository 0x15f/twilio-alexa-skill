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
    	$request_id = $request->input('request.requestId');
    	$intent_name = $request->input('request.type') === 'LaunchRequest' ? 'LaunchRequest' : $request->input('request.intent.name');
    	file_put_contents(storage_path() . '/json.json', json_encode($request, JSON_PRETTY_PRINT));

    	switch($intent_name)
    	{
    		case 'LaunchRequest':
		    	return response()->json([
		    		'version' => '1.0',
		    		'response' => [
			    		'outputSpeech' => [
			    			'type' => 'PlainText',
			    			'text' => 'Welcome to the Twilio Alexa assistant. Try saying send a voice message to start.',
			    		],
			    		'shouldEndSession' => false,
			    	],
		    	]);
    		break;
    		case 'SelectIntent':
    			$phone_number = $request->input('request.intent.slots.PHONE.value');

		    	return response()->json([
		    		'version' => '1.0',
		    		'response' => [
			    		'outputSpeech' => [
			    			'type' => 'PlainText',
			    			'text' => 'What message would you like to send?',
			    		],
			    		'shouldEndSession' => false,
			    	],
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

    	$message = Cache::get($id);

    	$twiml = new Twiml();
    	$twiml->say($message, [
    		'voice' => 'alice',
    	]);

	    $response = Response::make($twiml, 200);
	    $response->header('Content-Type', 'text/xml');
	    return $response;
    }
}
