<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Team;
use App\Event;
use App\Repositories\TeamMembersService;
use App\Repositories\EventService;
use App\Repositories\MatchesService;
use Illuminate\Support\Facades\Auth;

class TeamsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    
    protected $teamMembersService;
    protected $eventService;
    protected $matchesService;

    public function __construct(TeamMembersService $teamMembersService, EventService $eventService, MatchesService $matchesService){
        $this->teamMembersService = $teamMembersService;
        $this->eventService = $eventService;
        $this->matchesService = $matchesService;
    }

    public function index()
    {
        return Team::all();
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {

        $userId = Auth::id();

        if(count(Team::where('team_owner_id', $userId)->get())>0){
            return response()->json([
                'message' => 'You already has a team!',
            ], 400);
        }
        $team = Team::create($request->all());
        $team->team_owner_id = $userId;
        $team->team_members_id = $this->teamMembersService->setUp();
        $team->save();


        return response()->json([
            'message' => 'Team created',
            'data' => $team
        ], 200);
    }

    public function joinToEvent($id, Request $request){
        $userId = Auth::id();
        $team = Team::find($id);

        if(!$team){
            return response()->json([
                'message' => 'Team not found!',
            ], 400);
        }

        if($team->user->id != $userId){
            return response()->json([
                'message' => 'Its not your team!',
            ], 401);
        }

        $eventId = null;
        if(count($team->events()->where('event_id', $request->get('event_id'))->get())==0){
            $eventId = $request->get('event_id');
        }


        if($eventId){
            $event = Event::find($eventId);
            if(!$event){
                return response()->json([
                    'message' => 'Event not found!',
                ], 400);
            }
            if($event->slots > $event->teams()->count() && $event->joinable){
                $team->events()->attach($eventId);
                if($event->slots == $event->teams()->count()){
                    $this->eventService->switchToNotJoinable($event);
                    $this->matchesService->generateMatches($event);
                }
                return response()->json([
                    'message' => 'Sucesfuly joined to '. $event->name,
                ], 200);
            } else {
                return response()->json([
                    'message' => 'Event is full!',
                ], 400);
            }
        } else {
            return response()->json([
                'message' => 'You are in the event already',
            ], 400);
        }


    }

    public function leftEvent($id, Request $request){
        $userId = Auth::id();
        $team = Team::find($id);

        if(!$team){
            return response()->json([
                'message' => 'Team not found!',
            ], 400);
        }

        if($team->user->id != $userId){
            return response()->json([
                'message' => 'Its not your team!',
            ], 401);
        }


        $eventId = null;
        if(count($team->events()->where('event_id', $request->get('event_id'))->get())>0){
            $eventId = $request->get('event_id');
        }

        if($eventId){
            $event = Event::find($eventId);
            if(!$event){
                return response()->json([
                    'message' => 'Event not found!',
                ], 400);
            }
            $event = Event::find($eventId);
            if(!$event->joinable){
                return response()->json([
                    'message' => 'You cant leave events that already started',
                ], 400);
            }
            $team->events()->detach($eventId);
            return response()->json([
                'message' => 'Succesfully left event',
            ], 200);
        } else {
            return response()->json([
                'message' => 'You are not in this event',
            ], 400);
        }
    }

    public function updateMembers($id, Request $request){
        $userId = Auth::id();
        $team = Team::find($id);

        if($team->user->id != $userId){
            return response()->json([
                'message' => 'Its not your team!',
            ], 401);
        }


        if($team->members()->count()==0){
            $team->team_members_id = $this->teamMembersService->setUp();
            $team->save();
        }
        if($this->teamMembersService->updateMembers($team->team_members_id, $request->get('members_urls'))){
            return response()->json([
                'message' => 'Members saved.',
            ], 200);
        } else {
            return response()->json([
                'message' => 'Error',
            ], 400);
        }
    }


    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $team = Team::find($id);
        $teamMembers = $team->members()->get();
        $relatedEvents = $team->events()->get();
        if($team){
            return response()->json([
                'message' => 'Team found',
                'data' => $team,
                'members' => $teamMembers,
                'relatedEvents' => $relatedEvents
            ], 200);
        } else {
            return response()->json([
                'message' => 'Team not found'
            ], 404);
        }

    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Team $team)
    {
        $userId = Auth::id();

        if($team->user->id != $userId){
            return response()->json([
                'message' => 'Its not your team!',
            ], 401);
        }

        if($team->update($request->all())){
            return response()->json([
                'message' => 'Team updated',
            ], 200);
        } else {
            return response()->json([
                'message' => 'Cant update',
            ], 400);
        }

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $userId = Auth::id();
        $team = Team::find($id);


        if($team->user->id != $userId){
            return response()->json([
                'message' => 'Its not your team!',
            ], 401);
        }

        if(Team::destroy($id)){
            return response()->json([
                'message' => 'Deleted.',
            ], 200);
        } else{
            return response()->json([
                'message' => 'Cant delete',
            ], 400);
        }
    }
}
