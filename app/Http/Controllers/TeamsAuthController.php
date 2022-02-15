<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\TokenStore\TokenCache;
use Microsoft\Graph\Graph;
use Microsoft\Graph\Model;
use App\User;

class TeamsAuthController extends Controller
{
  public function signin()
  {
    try{

      // Initialize the OAuth client
      $oauthClient = new \League\OAuth2\Client\Provider\GenericProvider([
        'clientId'                => config('teams.OAUTH_APP_ID'),
        'clientSecret'            => config('teams.OAUTH_APP_PASSWORD'),
        'redirectUri'             => config('teams.OAUTH_REDIRECT_URI'),
        'urlAuthorize'            => config('teams.OAUTH_AUTHORITY').config('teams.OAUTH_AUTHORIZE_ENDPOINT'),
        'urlAccessToken'          => config('teams.OAUTH_AUTHORITY').config('teams.OAUTH_TOKEN_ENDPOINT'),
        'urlResourceOwnerDetails' => '',
        'scopes'                  => config('teams.OAUTH_SCOPES')
      ]);
  
      $authUrl = $oauthClient->getAuthorizationUrl();
      // dd($authUrl);
      // Save client state so we can validate in callback
      session(['oauthState' => $oauthClient->getState()]);
      
      // Redirect to AAD signin page
      return redirect()->away($authUrl);
    } catch(\Exception $e){
      dd($e);
    }
  }

  public function callback(Request $request)
  {
    \Log::info('app.requests', ['request' => $request->all()]);
    // dd($request);
    // Validate state
    $expectedState = session('oauthState');
    $request->session()->forget('oauthState');
    $providedState = $request->query('state');

    if (!isset($expectedState)) {
      // If there is no expected state in the session,
      // do nothing and redirect to the home page.
      return redirect('/');
    }

    if (!isset($providedState) || $expectedState != $providedState) {
      return redirect('/')
        ->with('error', 'Invalid auth state')
        ->with('errorDetail', 'The provided auth state did not match the expected value');
    }

    // Authorization code should be in the "code" query param
    $authCode = $request->query('code');
    if (isset($authCode)) {
      // Initialize the OAuth client
      $oauthClient = new \League\OAuth2\Client\Provider\GenericProvider([
        'clientId'                => config('teams.OAUTH_APP_ID'),
        'clientSecret'            => config('teams.OAUTH_APP_PASSWORD'),
        'redirectUri'             => config('teams.OAUTH_REDIRECT_URI'),
        'urlAuthorize'            => config('teams.OAUTH_AUTHORITY').config('teams.OAUTH_AUTHORIZE_ENDPOINT'),
        'urlAccessToken'          => config('teams.OAUTH_AUTHORITY').config('teams.OAUTH_TOKEN_ENDPOINT'),
        'urlResourceOwnerDetails' => '',
        'scopes'                  => config('teams.OAUTH_SCOPES')
      ]);

      try {
        // Make the token request
        $accessToken = $oauthClient->getAccessToken('authorization_code', [
          'code' => $authCode
        ]);
        dd($request,$accessToken,config('teams.OAUTH_SCOPES'),$accessToken->getToken());
        // dump($accessToken->getToken());
        $graph = new Graph();
        $graph->setAccessToken($accessToken->getToken());

        // $request = $graph->createRequest('GET', '/me?$select=displayName,mail,userPrincipalName')
        //            ->execute();
        // dd((object)$request->getBody());
        // $user = (object)$request->getBody();
        // dd($user);
        $user = $graph->createRequest('GET', '/me?$select=displayName,mail,userPrincipalName')
          // ->setReturnType(Model\User::class)
          ->execute();
        // dd($user);

        $tokenCache = new TokenCache();
        $tokenCache->storeTokens($accessToken, $user);
        dd($accessToken->getToken());
        return redirect('/');
        // // TEMPORARY FOR TESTING!
        // return redirect('/')
        //   ->with('error', 'Access token received')
        //   ->with('errorDetail', $accessToken->getToken());
      }
      catch (League\OAuth2\Client\Provider\Exception\IdentityProviderException $e) {
        \Log::info('Refresh Token for user error: '.$e->getMessage());
        return redirect('/')
          ->with('error', 'Error requesting access token')
          ->with('errorDetail', $e->getMessage());
      }
    }

    return redirect('/')
      ->with('error', $request->query('error'))
      ->with('errorDetail', $request->query('error_description'));
  }

  public function signout()
  {
    $tokenCache = new TokenCache();
    $tokenCache->clearTokens();
    return redirect('/');
  }
}