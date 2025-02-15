<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MessageController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
       // Lấy tất cả các sender_id từ bảng messages
        $senderIds = Message::pluck('sender_id')->unique(); // Lấy danh sách sender_id duy nhất

        $users = User::whereIn('id', $senderIds)
                    ->where('id', '<>', Auth::id()) // Ngoại trừ user hiện tại
                    ->get();


        return view('backend.admin.message.index',compact('users'));
    }

    public function takeNewUserMessage(Request $request)
    {
        $userIds = $request->input('user_ids', []); // Giả sử danh sách user_id được truyền qua input 'user_ids'
        $userIds[] = 1;
        // Gọi hàm getExcludedUsersByMessage để lấy các user_id không nằm trong danh sách
        $excludedUsers = Message::getExcludedUsersByMessage($userIds);

        // Trả về kết quả dưới dạng JSON
        return response()->json($excludedUsers);
    }
    public function takeCountUnseenMessage(Request $request)
    {
        $user_id=$request->user_id;
        $count = Message::countSeenMessagesBySender($user_id);

        // Trả về kết quả
        return response()->json(['count' => $count]);
    }
    public function changeStatus(Request $request)
    {
        $user_id=$request->user_id;
        // Lấy tất cả các tin nhắn đã được xem (seen = 1) của user_id
        $seenMessages = Message::getSeenMessagesByUserId($user_id);

        // Cập nhật thuộc tính seen của từng tin nhắn thành 0
        foreach ($seenMessages as $message) {
            $message->seen = 0;
            $message->save();
        }
    }
    public function getNewMessages(Request $request)
    {
        $senderId = $request->sender_id;
        $receiverId = Auth::id();
        $lastId = $request->last_id;
        $messages = Message::getMessagesAfterlastId($senderId, $receiverId, $lastId);
        return response()->json(['messages' => $messages]);
    }
    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {

    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'message' => ['required', 'string', 'max:255']
        ]);

        try {
            $message = new Message();
            $message->message = $request->message;
            $message->seen = 1;
            $message->sender_id = Auth::id();
            $message->received_id = $request->user_id;
            $message->created_at=now();
            $message->updated_at=now();
            $message->save();
            return redirect()->back();

        } catch (\Exception $e) {
            // return response()->json(['success' => false, 'error' => 'Có lỗi xảy ra.'], 500);
            return redirect()->back();

        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
