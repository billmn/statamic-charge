<?php
/**
 * Created by PhpStorm.
 * User: erin
 * Date: 2017-10-04
 * Time: 9:25 PM
 */

namespace Statamic\Addons\Charge\SuggestModes;

use Stripe\Plan;
use Stripe\Stripe;
use Statamic\Addons\Suggest\Modes\AbstractMode;

class StripePlansSuggestMode extends AbstractMode
{
    public function suggestions()
    {
        Stripe::setApiKey(env('STRIPE_SECRET_KEY'));

        return collect(Plan::all()->data)->map(function ($plan, $key) {
            return ['value' => $plan->id, 'text' => $plan->name];
        })->values()->all();
    }
}