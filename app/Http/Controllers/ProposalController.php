<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateProposalRequest;
use App\Http\Requests\ProposalRequest;
use App\Http\Requests\UpdateProposalRequest;
use App\Models\Invoice;
use App\Models\Proposal;
use App\Models\ProposalTemplate;
use App\Ninja\Datatables\ProposalDatatable;
use App\Ninja\Repositories\ProposalRepository;
use App\Services\ProposalService;
use Auth;
use Input;
use Session;
use View;

class ProposalController extends BaseController
{
    protected $proposalRepo;
    protected $proposalService;
    protected $entityType = ENTITY_PROPOSAL;

    public function __construct(ProposalRepository $proposalRepo, ProposalService $proposalService)
    {
        $this->proposalRepo = $proposalRepo;
        $this->proposalService = $proposalService;
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        return View::make('list_wrapper', [
            'entityType' => ENTITY_PROPOSAL,
            'datatable' => new ProposalDatatable(),
            'title' => trans('texts.proposals'),
        ]);
    }

    public function getDatatable($expensePublicId = null)
    {
        $search = Input::get('sSearch');
        $userId = Auth::user()->filterId();

        return $this->proposalService->getDatatable($search, $userId);
    }

    public function create(ProposalRequest $request)
    {
        $account = auth()->user()->account;

        $data = [
            'account' => $account,
            'proposal' => null,
            'method' => 'POST',
            'url' => 'proposals',
            'title' => trans('texts.new_proposal'),
            'invoices' => Invoice::scope()->with('client.contacts', 'client.country')->unapprovedQuotes()->orderBy('id')->get(),
            'templates' => ProposalTemplate::whereAccountId($account->id)->orWhereNull('account_id')->orderBy('name')->get(),
            'invoicePublicId' => $request->invoice_id,
            'templatePublicId' => $request->proposal_template_id,
        ];

        return View::make('proposals.edit', $data);
    }

    public function show($publicId)
    {
        Session::reflash();

        return redirect("proposals/$publicId/edit");
    }

    public function edit(ProposalRequest $request)
    {
        $account = auth()->user()->account;
        $proposal = $request->entity();

        $data = [
            'account' => $account,
            'proposal' => $proposal,
            'method' => 'PUT',
            'url' => 'proposals/' . $proposal->public_id,
            'title' => trans('texts.edit_proposal'),
            'invoices' => Invoice::scope()->with('client.contacts', 'client.country')->unapprovedQuotes($proposal->invoice_id)->orderBy('id')->get(),
            'templates' => ProposalTemplate::whereAccountId($account->id)->orWhereNull('account_id')->orderBy('name')->get(),
            'invoicePublicId' => $proposal->invoice ? $proposal->invoice->public_id : null,
            'templatePublicId' => $proposal->proposal_template ? $proposal->proposal_template->public_id : null,
        ];

        return View::make('proposals.edit', $data);
    }

    public function store(CreateProposalRequest $request)
    {
        $proposal = $this->proposalService->save($request->input());

        Session::flash('message', trans('texts.created_proposal'));

        return redirect()->to($proposal->getRoute());
    }

    public function update(UpdateProposalRequest $request)
    {
        $proposal = $this->proposalService->save($request->input(), $request->entity());

        Session::flash('message', trans('texts.updated_proposal'));

        $action = Input::get('action');
        if (in_array($action, ['archive', 'delete', 'restore'])) {
            return self::bulk();
        }

        return redirect()->to($proposal->getRoute());
    }

    public function bulk()
    {
        $action = Input::get('action');
        $ids = Input::get('public_id') ? Input::get('public_id') : Input::get('ids');

        $count = $this->proposalService->bulk($ids, $action);

        if ($count > 0) {
            $field = $count == 1 ? "{$action}d_proposal" : "{$action}d_proposals";
            $message = trans("texts.$field", ['count' => $count]);
            Session::flash('message', $message);
        }

        return redirect()->to('/proposals');
    }
}