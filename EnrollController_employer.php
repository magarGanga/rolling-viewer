<?php

namespace App\Http\Controllers\employer;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Booth;
use App\BoothAssistantZoom;
use App\BoothReserve;
use App\BoothTicketType;
use App\Category;
use App\EnrollReservation;
use App\EnrollPhoto;
use App\Imagetool;
use App\EnrollVideo;
use App\Employees;
use App\EnrollEmployerStream;
use App\EnrollInvoice;
use Pusher\Pusher;
use Symfony\Component\HttpFoundation\Response;

class EnrollController extends Controller
{


    public function addnew(Request $request)
    {

        $categories = Category::get();
        $booths = Booth::get();
        $datas['placeholder'] = Imagetool::mycrop('no-image.png', 60,60);

        if($request->isMethod('post')){

            // dd(auth()->guard('employer')->user()->employers_id);
            $request->validate([
                'fair_detail' => 'required|mimes:pdf,doc,docx',
                'banner_image' => 'required',
                'exhibition_category' => 'required',
                'company_name' => 'required',
                'intro_video' => 'required',
                'description' => 'required',
                'booth_name' => 'required',
                'booth_type' => 'required',
                'item_price' => 'required',
                // 'zoom' => 'required',
                'company_site' => 'required',
                'photo' => 'required',
                'seo_url' => 'required',


            ]);

            $datas = $request->all();

            $reservation = new EnrollReservation();
            $reservation->category_id = $datas['exhibition_category'];
            $reservation->employer_id = auth()->guard('employer')->user()->employers_id;

            $reservation->company_name = $datas['company_name'];
            $reservation->seo_url = $datas['seo_url'];
            $reservation->company_website = $datas['company_site'];
            $intro_id = $this->YoutubeID($datas['intro_video']);
            $reservation->intro_video = $intro_id ;
            $reservation->banner_file = $datas['banner_image'];
            $reservation->description = $datas['description'];
            $reservation->payment_status = '0';

              //Fair detail
            if($request->hasFile('fair_detail'))
              {
                $file_temp = $request->file('fair_detail');
                if($file_temp->isValid()){
                    $filenameWithExtension = $file_temp->getClientOriginalName();
                    $extension = $file_temp->getClientOriginalExtension();
                    $filenameWithoutExtension = pathinfo($filenameWithExtension, PATHINFO_FILENAME);
                    $filenameToStore = $filenameWithoutExtension.'_'.time().'.'.$extension;
                    $path = $file_temp->storeAs('uploads/companies/fairDetails', $filenameToStore);
                    $reservation->fair_detail = $path;

                }

            }
            $reservation->save();
            $reservation_id = $reservation->id;

            //Video
            if(isset($request->video)){
                foreach($request->video as $key => $video){
                    if(trim($video['vlink']) != ''){
                        $vid = $this->YoutubeID($video['vlink']);
                        $data = [
                            'reservation_id' => $reservation_id,
                            'title' => $video['vtitle'],
                            'link' => $vid,
                        ];
                        EnrollVideo::create($data);
                    }
                }
            }
            //Photo
            if (isset($request->photo)) {
                foreach($request->photo as $key => $photo) {
                    if (trim($photo['title']) != '') {
                    $data = [
                      'reservation_id' => $reservation_id,
                      'title' => $photo['title'],
                      'image' => $photo['image'],
                      'description' => $photo['description']
                    ];
                    EnrollPhoto::create($data);
                  }
                }
            }

            // Booth Reserve
            foreach ($datas['booth_name'] as $key=>$val)
            {

                $booth = new BoothReserve();
                $booth->reservation_id = $reservation_id;
                $booth->employer_id = auth()->guard('employer')->user()->employers_id;
                $temp_name = Booth::select('booth_name')->where('id', $val)->first();
                $booth->booth_name = $temp_name['booth_name'];
                $type_id = $datas['booth_type'][$key];
                $temp = BoothTicketType::select('ticket_name', 'price')->where('id', $type_id)->first();
                $booth->booth_type = $temp['ticket_name'];
                $booth->price = $temp['price'];
                $booth->save();
            }

            // zoom detail for Company Speaker in Booth
            // if (isset($request->zoom)) {
            //     foreach($request->zoom as $key => $zoom) {
            //         if (trim($zoom['zlink']) != '') {
            //         $data = [
            //           'reservation_id' => $reservation_id,
            //           'url' => $zoom['zlink'],
            //           'meeting_id' => $zoom['zid'],
            //           'password' => bcrypt($zoom['password'])
            //         ];
            //         BoothAssistantZoom::create($data);
            //       }
            //     }
            // }

                $total_price = BoothReserve::where('reservation_id', $reservation_id)->sum('price');
                EnrollReservation::where('id', $reservation_id)->update([
                    'total_price' => $total_price
            ]);


        return redirect('employer/enroll/all-detail')->with('message', "Successufully reserved");

        }
        return view('employer.enroll.new_enroll', compact('categories', 'booths', 'datas'));
    }

    public function YoutubeID($url)
    {
        if(strlen($url) > 11)
        {
            if (preg_match('%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/ ]{11})%i', $url, $match))
            {
                return $match[1];
            }
            else
                return false;
        }

        return $url;
    }

    public function enrollDetail()
    {
        $reservations = EnrollReservation::where('employer_id', auth()->guard('employer')->user()->employers_id)
        ->with('boothreserves')
        ->orderBy('created_at', 'desc')
        ->get();
        return view('employer.enroll.enroll_detail', compact('reservations'));
    }

    public function editEnroll($id=null)
    {
        $datas['placeholder'] = Imagetool::mycrop('no-image.png', 60,60);

        $reservations = EnrollReservation::where('id', $id)->first();
        $categories = Category::get();
        return view('employer.enroll.editenroll', compact('reservations','categories', 'datas'));
    }

    public function updateEnroll(Request $request, $id=null)
    {
        $data = $request->all();

        if ($request->hasFile('fair_detail')) {
            $file_temp = $request->file('fair_detail');
            if ($file_temp->isValid()) {
                $filenameWithExtension = $file_temp->getClientOriginalName();
                $extension = $file_temp->getClientOriginalExtension();
                $filenameWithoutExtension = pathinfo($filenameWithExtension, PATHINFO_FILENAME);
                $filenameToStore = $filenameWithoutExtension.'_'.time().'.'.$extension;
                $path = $file_temp->storeAs('uploads/companies/fairDetails/', $filenameToStore);
                $fair_detail = $path;
            }
        }
        $vid = $this->YoutubeID($data['intro_video']);
        EnrollReservation::where('id', $id)->update([
        'category_id' => $data['exhibition_category'],
        'company_name' => $data['company_name'],
        'employer_id' => auth()->guard('employer')->user()->employers_id,
        'intro_video_link' => $vid,
        'description' => $data['description'],
        'payment_status' => '0',
        'fair_detail' => $fair_detail,
        'banner_file' => $data['banner_image']
        ]);

        return redirect('employer/enroll/detail')->with('message', 'Enroll Updated Successfully');


    }

    public function deleteEnroll($res_id = null)
    {
       EnrollReservation::where('id', $res_id)
            ->with('boothreserves')
            ->with('photos')
            ->with('videos')
            ->delete();
        return redirect()->back();
    }
    public function paymentDetail()
    {

        $reserves = BoothReserve::where('employer_id', auth()->guard('employer')->user()->employers_id)->where('status', 0)->get();
        return view('employer.enroll.payment_detail', compact('reserves'));
    }



    public function getParticipateUsers(Request $request)
    {
        $data['contacts'] = [];
        // $contacts =\App\EmployeeRegistration::get(); Must get data from registeration
        $contacts = \App\UserCircle::where('status', 1)->get();

        foreach ($contacts as $key => $contact) {
          $number_of = false;
          $chk_msg = \App\ChatMessage::where('message_from', auth()->guard('employer')->user()->employer_id )->where('message_to', $contact->staff_id)->where('view_status','!=', '1')->count();
          if ($chk_msg > 0) {
            $number_of = $chk_msg;
          }

          $data['contacts'][] = [
            'id'    => $contact->staff_id,
            'name'  => Employees::getName($contact->staff_id),
            'image'  => Employees::getPhoto($contact->staff_id),
            'status'  => Employees::CheckOnline($contact->staff_id),
            'number_of' => $number_of,

          ];
        }

      $return_data = view('employer.enroll.messages.message_users')->with('data',$data)->render();
      return response()->json($return_data);
    }

    public function GetChatBox($user_id='', Request $request)
    {

        $page = 0;
        if ($request->page) {
            $page= $request->page;
        }
        $limit = 20;
        $start = $page * $limit;
        $data = [];

        $my_id = auth()->guard('employer')->user()->employers_id;


        // Make read all unread message
        \App\ChatMessage::where(['message_from' => $user_id, 'message_to' => $my_id])->update(['view_status' => 1]);

        // Get all message message_from selected user
        $messages = \App\ChatMessage::where(function ($query) use ($user_id, $my_id) {
            $query->where('message_from', $user_id)->where('message_to', $my_id)->where('to_delete', '!=', '1');
        })->oRwhere(function ($query) use ($user_id, $my_id) {
            $query->where('message_from', $my_id)->where('message_to', $user_id)->where('from_delete', '!=', '1');
        })->orderBy('id','desc');


        $data['message'] = $messages->skip($start)->take($limit)->get()->reverse();
        $totmsg = $messages->count();
        $fetmsg = ($page + 1) * $limit;
        $data['ldmr'] = 1;
        if ($totmsg > $fetmsg) {
          $data['ldmr'] = 2;
        }
        $data['user_id'] = $user_id;
        $data['name'] = Employees::getName($user_id);
        $data['image'] = Employees::getPhoto($user_id);
        $data['page'] = $page + 1;
        if ($page > 0) {
          $return_data['data'] = view('employer.enroll.messages.chats')->with('data',$data)->render();
          $return_data['ldmr'] = $data['ldmr'];
        } else{
          $return_data = view('employer.enroll.messages.chat_box')->with('data',$data)->render();
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

        $from = auth()->guard('employer')->user()->employers_id;
        $sender_name = \App\Employers::getName($from);

        $to = $request->receiver_id;
        $message = $request->message;

        $data = new \App\ChatMessage();
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
                                    <img src="'.asset(\App\Employers::getEmpLogo($data->message_from)).'">
                                    </div>
                                    <div class="receive-msg-desc rounded text-center mt-1 ml-1 pl-2 pr-2">
                                        <p class="mb-0 mt-1 pl-2 pr-2 rounded">'.$data->message.'</p>

                                    </div>
                                </div>
                                <i id="delete_'.$data->id.'" class="fa fa-remove delete_chat"></i>
                            </li>';

        $pda = ['from' => $from, 'to' => $to, 'html' => $html, 'sender_name' => $sender_name ]; // sending from and to user id when pressed enter

        $pusher->trigger('my-channel', 'my-event', $pda);

        $json['data'] = '<li id="chat_'.$data->id.'" class="pl-2 pr-2 rounded text-white text-center send-msg mb-1 unread_message">'.$data->message.'<i id="delete_'.$data->id.'" class="fa fa-remove delete_chat"></i></li>';

        return response()->json($json);

    }

    public function deleteBooth($id=null)
    {
        BoothReserve::where('id', $id)->delete();
        return redirect()->back();
    }
    public function getBoothType($id=null )
    {
        $ticket_type['data'] = BoothTicketType::where('booth_id', $id)->get();
        echo json_encode($ticket_type);

    }

    public function getBoothPrice($id=null)
    {
        $ticket_price['data'] = BoothTicketType::where('id',$id)->first();
        echo json_encode($ticket_price);
    }

    public function dashboard()
    {
        $datas = EnrollReservation::where('employer_id', auth()->guard('employer')->user()->employers_id)
        ->with('boothreserves')
        ->with('viewers')
        ->orderBy('created_at', 'desc')
        ->paginate(50);

        return view('employer.enroll.dashboard', compact('datas'));
    }

    public function livestream($slug = null)
    {

        $data = EnrollReservation::where('seo_url', $slug)->first();
        $data['channel'] = $slug;
        return view('employer.enroll.broadcast', compact('data'));
    }

    public function storeStartTime(Request $request){
        EnrollEmployerStream::where('channel', $request['channel'])->update([
            'start_time' => now()
        ]);

        return 'Start Time';
    }

    public function storeEndTime(Request $request){
        EnrollEmployerStream::where('channel', $request['channel'])->update([
            'end_time' => now()
        ]);

        return 'End Time';
    }


}
