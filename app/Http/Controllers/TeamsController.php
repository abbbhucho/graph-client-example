<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class TeamsController extends Controller
{
  public function welcome()
  {
    $viewData = $this->loadViewData();

    return view('welcome', $viewData);
  }

//   public function check(){
//     dd(config('teams.OAUTH_AUTHORIZE_ENDPOINT'));
//   }
}