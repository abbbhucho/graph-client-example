<?php

namespace App\TokenStore;

class TokenCache {
  public function storeTokens($accessToken, $user) {
    session([
      'accessToken' => $accessToken->getToken(),
      'refreshToken' => $accessToken->getRefreshToken(),
      'tokenExpires' => $accessToken->getExpires(),
      'userName' => $user->getDisplayName(),
      'userEmail' => null !== $user->getMail() ? $user->getMail() : $user->getUserPrincipalName(),
    ]);
  }

  public function clearTokens() {
    session()->forget('accessToken');
    session()->forget('refreshToken');
    session()->forget('tokenExpires');
    session()->forget('userName');
    session()->forget('userEmail');
  }

  public function getAccessToken() {
    // Check if tokens exist
    if (empty(session('accessToken')) ||
        empty(session('refreshToken')) ||
        empty(session('tokenExpires'))) {
      return '';
    }

    // Check if token is expired
    $aboutToExpire = time() + 300; // current + 5 min
    if (session('tokenExpires') <= $aboutToExpire) {
        // Token is expired (or very close to it)
        
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
            $newToken = $oauthClient->getAccessToken('refresh_token', [
                'refresh_token' => session('refreshToken')
            ]);

            // Storing the new values
            $this->updateTokens($newToken);

            return $newToken->getToken();
        }
        catch (League\OAuth2\Client\Provider\Exception\IdentityProviderException $e) {
            \Log::info('Refresh Token for user error: '.$e->getMessage());
            return '';
        }
    }
    // Token is still valid, just return it
    return session('accessToken');
  }

  public function updateTokens($accessToken) {
    session([
      'accessToken' => $accessToken->getToken(),
      'refreshToken' => $accessToken->getRefreshToken(),
      'tokenExpires' => $accessToken->getExpires()
    ]);
  }
}