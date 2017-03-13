<?php

namespace Statamic\Addons\Charge;

use Statamic\API\User;
use Statamic\Extend\Listener;
use Statamic\CP\Navigation\Nav;
use Statamic\CP\Navigation\NavItem;

class ChargeListener extends Listener
{
    /**
     * The events to be listened for, and the methods to call.
     *
     * @var array
     */
    public $events = [
        'Form.submission.creating' => 'chargeForm',
        'content.saved' => 'chargeEntry',
        'user.registering' => 'register',
        'Charge.cancel' =>  'cancel',
        'Charge.resubscribe' => 'resubscribe',
        'cp.nav.created'  => 'nav',
        'cp.add_to_head' => 'addToHead',
   ];

    /** @var  \Statamic\Addons\Charge\Charge */
    private $charge;

    public function init()
    {
        $this->charge = new Charge;
    }

    /**
     * @param \Statamic\Data\Entries\Entry $entry
     *
     */
    public function chargeEntry($entry)
    {
        // only do something if we're in the right collection
        // @todo hack in here due to Workshop not differentiating between editing and creating
        if (request()->has('process_payment') &&
            in_array($entry->collectionName(), $this->getConfig('charge_collections')))
        {
            try
            {
                // get paid
                $charge = $this->charge->charge($this->charge->getDetails($entry->data()));

                // if there's a user logged in, store the details
                if ($user = User::getCurrent())
                {
                    $this->charge->updateUser($user, $charge);
                }
            }
            catch (\Exception $e)
            {
                \Log::error($e->getMessage());
                // @todo how return an error?
            }
        }
    }

    /**
     * @param \Statamic\Forms\Submission $submission
     *
     * @return \Statamic\Forms\Submission|array
     */
    public function chargeForm($submission)
    {
        // only do something if we're on the right formset
        if ($submission->formset()->name() === $this->getConfig('charge_formset'))
        {
            try
            {
                // get paid
                $charge = $this->charge->charge($this->charge->getDetails($submission->data()));

                // if there's a user logged in, store the details
                if ($user = User::getCurrent())
                {
                    $this->charge->updateUser($user, $charge);
                }

                // add the charge id to the submission
                $submission->set('customer_id', $charge['customer']['id']);

                $this->flash->put('details', $charge);
            }
            catch (\Exception $e)
            {
                \Log::error($e->getMessage());
                return array('errors' => array($e->getMessage()));
            }
        }

        return $submission;
    }

    /**
     * @param \Statamic\Data\Users\User $user
     *
     * @return \Statamic\Data\Users\User|array
     */
    public function register($user)
    {
        // only do something if there's an amount or a plan
        if (request()->has('amount') || request()->has('plan'))
        {
            try
            {
                $charge = $this->charge->charge($this->charge->getDetails([ 'email' => $user->email() ]));

                // Add the relevant Stripe details
                $this->charge->updateUser($user, $charge);

                $this->flash->put('details', $charge);
            } catch (\Exception $e)
            {
                \Log::error($e->getMessage());
                return array('errors' => array($e->getMessage()));
            }
        }
        return $user;
    }

    /**
     * Add Charge to the side nav
     * @param  Nav    $nav [description]
     * @return void
     */
    public function nav(Nav $nav)
    {
        $charge = (new NavItem)->name('Charge')->route('charge')->icon('credit-card');
        $nav->addTo('tools', $charge);
    }

    /**
     * Need some JS in the CP to deal with the refund confirmations
     *
     * @return string
     */
    public function addToHead()
    {
        return $this->js->tag("charge-cp") . PHP_EOL;
    }

    public function cancel()
    {
        $this->charge->cancel($this->getId());
    }

    public function resubscribe()
    {
        $this->charge->resubscribe($this->getId());
    }

    /**
     * Grab the id from the URL
     *
     * @return string
     */
    private function getId()
    {
        return request()->segment(4);
    }

}