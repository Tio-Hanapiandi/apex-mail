<?php

namespace App\Http\Controllers;

use App\Models\CalendarEvent;
use App\Models\Contact;
use App\Models\MailAccount;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class GroupwareController extends Controller
{
    protected function getActiveAccount(): MailAccount
    {
        $accountId = Session::get('active_account_id');
        $account = $accountId ? MailAccount::find($accountId) : null;

        return $account ?? MailAccount::where('username', 'budi')->first() ?? MailAccount::first();
    }

    /**
     * Contacts and Global Address List (GAL).
     */
    public function contacts(Request $request)
    {
        $currentAccount = $this->getActiveAccount();
        $query = $request->query('q');

        $contactsQuery = Contact::where(function ($q) use ($currentAccount) {
            $q->where('mail_account_id', $currentAccount->id)
              ->orWhere('domain_id', $currentAccount->domain_id)
              ->orWhere('is_gal', true);
        });

        if ($query) {
            $contactsQuery->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('email', 'like', "%{$query}%")
                  ->orWhere('job_title', 'like', "%{$query}%")
                  ->orWhere('company', 'like', "%{$query}%");
            });
        }

        $contacts = $contactsQuery->orderBy('is_gal', 'desc')->orderBy('name')->get();

        return view('groupware.contacts', compact('currentAccount', 'contacts', 'query'));
    }

    /**
     * Calendar and scheduled meetings.
     */
    public function calendar(Request $request)
    {
        $currentAccount = $this->getActiveAccount();
        $events = CalendarEvent::where('mail_account_id', $currentAccount->id)
            ->orderBy('start_time')
            ->get();

        return view('groupware.calendar', compact('currentAccount', 'events'));
    }
}
