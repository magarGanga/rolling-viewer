<?php

namespace App\Http\Controllers\front;

use App\Category;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Enroll;
use App\EnrollReservation;
use App\User;
use App\ChatMessage;
use App\EmployerUser;
use App\EnrollAudienceViewer;
use App\EnrollEmployerStream;
use Pusher\Pusher;
use App\EnrollViewer;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Cache;
use PDF;

class EnrollController extends Controller
{


    public function show($slug=null)
    {
        $enroll_type = Enroll::get();
        $category = Category::where('seo_url', $slug)->first();
        $companies = EnrollReservation::where('category_id',$category->id)->where('publish_status', '1')->get();
        return view('front.enroll.show', compact('enroll_type', 'companies'));
    }

    public function homePage($slug=null)
    {
        $enroll_type = Enroll::get();
        $company_detail = EnrollReservation::where('seo_url', $slug)->where('publish_status', '1')->orderBy('created_at', 'asc')->first();

        $p_viewer = EnrollViewer::where('employee_id', auth()->guard('employee')->user()->id)->where('reservation_id', $company_detail->id)->first();
        if ($p_viewer == null) {
            EnrollViewer::create([
                'reservation_id' => $company_detail->id,
                'employee_id' => auth()->guard('employee')->user()->id
            ]);
        }else if($p_viewer->employee_id == null){
            EnrollViewer::create([
                'reservation_id' => $company_detail->id,
                'employee_id' => auth()->guard('employee')->user()->id
            ]);
        }

        $views = $company_detail->views + 1;
        EnrollReservation::where('id', $company_detail->id)->update([
            'views' => $views,
        ]);

        $business_user = \App\Employers::where('id', $company_detail->employer_id)->first();
        $photos = $company_detail->photos;
        return view('front.enroll.homepage', compact('enroll_type', 'company_detail', 'photos', 'business_user', 'views'));
    }

    public function downloadBusinessProfile($id=null)
    {
        $business_profile = EnrollReservation::where('id', $id)->firstorFail();
        $file = storage_path().'/app/'.$business_profile->fair_detail;
        $headers = [
            'Content-Type' => 'application/pdf',
         ];

        return response()->download($file, 'details.pdf', $headers );





    }

    public function getBusinessUser(Request $request)
    {

        $data['contacts'] = [];


        $contacts = \App\Employers::where('id', $request->receiver_id)->where('status', 1)->get();

        foreach ($contacts as $key => $contact) {
          $number_of = false;
          $chk_msg = \App\ChatMessage::where('message_from', auth()->guard('employee')->user()->id)->where('message_to', $request->receiver_id)->where('view_status','!=', '1')->count();
          if ($chk_msg > 0) {
            $number_of = $chk_msg;
          }

          $data['contacts'][] = [
            'id'    => $contact->id,
            'name'  => $contact->name,
            'image'  => $contact->image,
            'status'  => $contact->status,
            'number_of' => $number_of,

          ];
        }

      $return_data = view('front.enroll.messages.message_user')->with('data',$data)->render();
      return response()->json($return_data);
    }

    public function GetChatBox(Request $request)
    {
        $business_user_id = $request->business_user_id;
        // return $business_user_id;
        $page = 0;
        if ($request->page) {
            $page= $request->page;
        }
        $limit = 20;
        $start = $page * $limit;
        $data = [];
        $my_id = auth()->guard('employee')->user()->id;

        // Make read all unread message
        ChatMessage::where(['message_from' => $request->business_user_id, 'message_to' => $my_id])->update(['view_status' => 1]);

        // Get all message message_from selected user
        $messages = ChatMessage::where(function ($query) use ($business_user_id, $my_id) {
            $query->where('message_from', $business_user_id)->where('message_to', $my_id)->where('to_delete', '!=', '1');
        })->oRwhere(function ($query) use ($business_user_id, $my_id) {
            $query->where('message_from', $my_id)->where('message_to', $business_user_id)->where('from_delete', '!=', '1');
        })->orderBy('id','desc');


        $data['message'] = $messages->skip($start)->take($limit)->get()->reverse();
        $totmsg = $messages->count();
        $fetmsg = ($page + 1) * $limit;
        $data['ldmr'] = 1;

        if ($totmsg > $fetmsg) {
            $data['ldmr'] = 2;
        }

        $data['user_id'] = $business_user_id;
        $data['name'] = \App\Employers::getName($business_user_id);
        $data['image'] = \App\Employers::getPhoto($business_user_id);
        $data['page'] = $page + 1;

        if ($page > 0) {
            $return_data['data'] = view('front.enroll.messages.chats')->with('data',$data)->render();
            $return_data['ldmr'] = $data['ldmr'];
        } else{
            $return_data = view('front.enroll.messages.chat_box')->with('data',$data)->render();
        }

        return response()->json($return_data);
    }

    public function sendMessage(Request $request)
    {
        $json = [];

        $this->validate($request,[
            'receiver_id' => 'required|integer',
            'message'   => 'required'
        ]);
            $from = auth()->guard('employee')->user()->id;
            $to = $request->receiver_id;
            $message = $request->message;
            $sender_name = \App\Employees::getName($from);

            $data = new ChatMessage();
            $data->message_from = $from;
            $data->message_to = $to;
            $data->message = $message;
            $data->view_status = 0; // message will be unread when sending message
            $data->from_delete = 0;
            $data->to_delete = 0;
            $data->save();

            // pusher
            $options = array(
                'cluster' => 'ap2',
                'useTLS' => true

            );

            $pusher = new Pusher(
                env('PUSHER_APP_KEY'),
                env('PUSHER_APP_SECRET'),
                env('PUSHER_APP_ID'),
                $options
            );
            $html = '<li id="chat_'.$data->id.'" class="p-1 rounded mb-1">
                                    <div class="receive-msg">
                                        <div class="receive-msg-img">
                                            <img src="'.asset(\App\Employees::getPhoto($data->message_from)).'">
                                        </div>
                                        <div class="receive-msg-desc rounded text-center mt-1 ml-1 pl-2 pr-2">
                                            <p class="mb-0 mt-1 pl-2 pr-2 rounded">'.$data->message.'</p>

                                        </div>
                                    </div>
                                    <i id="delete_'.$data->id.'" class="fa fa-remove delete_chat"></i>
                                </li>';

            $pda = ['from' => $from, 'to' => $to, 'html' => $html, 'sender_name' => $sender_name]; // sending from and to user id when pressed enter
            $pusher->trigger('my-channel', 'my-event', $pda);

            $json['data'] = '<li id="chat_'.$data->id.'" class="pl-2 pr-2 rounded text-white text-center send-msg mb-1 unread_message">'.$data->message.'<i id="delete_'.$data->id.'" class="fa fa-remove delete_chat"></i></li>';
            return response()->json($json);

    }

    public function audience($slug=null)
    {

        $data = EnrollReservation::where('seo_url', $slug)->first();
        $stream_data = EnrollEmployerStream::where('channel', $data)->first();
        $data['channel'] = $slug;
        $data['viewer'] = 1;

        return view('front.enroll.audience', compact('data', 'stream_data'));
    }

    public function audienceViewer(Request $request)
    {

        $stream_data = EnrollEmployerStream::where('channel', $request['channel'])->first();
        $employee = auth()->guard('employee')->user();
        $data = EnrollReservation::where('seo_url', $request['channel'])->first();
        $employer_id = $data['employer_id'];
        $reservation_id = $data['id'];
        $camera_profile = '720p_6';
        $message = '';
        $viewer_count = '';
        $counter = '';
        $html='';

        if($stream_data != '' && $stream_data->channel == $request['channel']){
            //if entry channel is equal to database only update employee_id and count
                $array_data = json_decode($stream_data->employee_id);
                if(in_array($employee->id, $array_data)) {
                    $viewer_count = $stream_data->total_count;
                    $message = 'old_user';

                }else{
                    array_push($array_data, $employee->id);
                    $viewer_count = $stream_data->total_count + 1;
                    $counter = $viewer_count;
                    EnrollEmployerStream::where('channel', $request['channel'])->update([
                        'employee_id' => json_encode($array_data),
                        'total_count' => $viewer_count,
                        'counter' => $counter,
                    ]);
                    $message = 'new_user';


                }
        }else{
            $viewer_count = 1;
            $counter = 1;

            EnrollEmployerStream::create([
                'employee_id' =>  json_encode([$employee->id]),
                'employer_id' =>  $employer_id,
                'reservation_id' => $reservation_id,
                'channel' => $request['channel'],
                'camera_profile'=> $camera_profile,
                'total_count' => $viewer_count,
                'counter' => $counter,
            ]);
            $message = 'new_user';

        }

        //   pusher
        $options = array(
            'cluster' => 'ap2',
            'useTLS' => true
        );
        $pusher = new Pusher(
            env('PUSHER_APP_KEY'),
            env('PUSHER_APP_SECRET'),
            env('PUSHER_APP_ID'),
        $options
        );

        $html = '<li class="list-group-item" id="user_viewer_'.$employee->id.'">'.$employee->email.'</li>';
        $temp = ['user_id' => $employee->id, 'viewer_count' => $viewer_count, 'counter' => $counter, 'html' => $html, 'type' => 'joinstream', 'message' => $message];
        $pusher->trigger('my-audience', 'my-broadcast', $temp);
        $pusher->trigger('my-channel', 'my-event', $temp);

    }

    public function streamLeave(Request $request)
    {

        $enroll_stream = EnrollEmployerStream::where('channel', $request['channel'])->first();
        if($enroll_stream->counter > 1)
        {
            $counter = $enroll_stream->couter - 1 ;
        }else{
            $counter = 0;
        }
        $enroll_stream->counter = $counter;
        $enroll_stream->save();

        $user = auth()->guard('employee')->user();
          // pusher
          $options = array(
            'cluster' => 'ap2',
            'useTLS' => true
        );

        $pusher = new Pusher(
            env('PUSHER_APP_KEY'),
            env('PUSHER_APP_SECRET'),
            env('PUSHER_APP_ID'),
            $options
        );
        $temp = ['user_id' => $user->id,'type' => 'leavestream', 'count' => $counter];
        $pusher->trigger('my-audience', 'my-broadcast', $temp);
    }

    //Hold for future
    public function joinChannel()
    {
        return view('front.enroll.videochannel');
    }






}
