<?php

namespace App\Http\Controllers\api\v1;

use App\Booking;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\ParkingLotDashboard;
use App\User;
use Facade\FlareClient\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $users = User::all();

        if (!empty($users)) {
            return success_response(['data' => $users]);
        }

        return error_response("No users found!");
    }

    /**
     * Add or update the parking capacity in the system.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function updateParkingSlots(Request $request)
    {
        $requestData = $request->all();

        if (empty($requestData)) {
            return invalidRequest();
        }

        //Todo need to check user permissions to update dashboard data

        $data = ParkingLotDashboard::first();
        if(!empty($data)){
            //Todo on update with existing data currenlty not allowing
            return error_response('Data update not allowed!');
        }
        $arrData['total_parking_capacity'] = $requestData['total_parking_capacity'] ?? 0;

        if ($arrData['total_parking_capacity'] > 0) {
            $arrData['reserved_parking_capacity'] = round((SPECIAL_CATEGORY_RESRVATION * $arrData['total_parking_capacity']) / 100);
            $arrData['not_reserved_parking_capacity'] = $arrData['total_parking_capacity'] - $arrData['reserved_parking_capacity'];
            $data = ParkingLotDashboard::updateOrCreate($arrData);
            if ($data) {
                return response()->json(["message" => "Data updated suceesfully", "data" => $data->toArray()]);
            }
        }
    }

    /**
     * Booking the parking slot for the user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function bookParkingSlot(Request $request)
    {
        $requestData = $request->all();

        $type = $requestData['booking_type'] ?? 'general';
        $parking_lot_no = $requestData['parking_lot_no'] ?? '';
        $user_id = Auth::user()->id;

        $dashboard_data = $this->getParkingDashboardData();
        if ($dashboard_data[$type] <= 0) {
            return error_response("Sorry no slots available at this time, please try again later");
        }

        $booking_statuses = config('custom_config.BOOKING_STATUS');
        $booking_types = config('custom_config.BOOKING_TYPES');
        //Checking only one active parking slot per user at a time
        $booking_availability_check = Booking::where('user_id', $user_id)->whereIn('status', ['booked', 'check_in'])->count();
        if ($booking_availability_check) {
            return invalidRequest("Only one active booking is allowed per a user");
        }

        $available_slots = $this->getAvailableParkingSlots(1);
        if (!empty($parking_lot_no) && in_array($parking_lot_no, $available_slots[$type])) {
            $arrData['parking_no'] = $parking_lot_no;
        }else if($available_slots['general'][0]){
            //This is to assign a reserved user to general parking space if reserved space occupied completely
            $arrData['parking_no'] = $available_slots['general'][0];
        }else {
            return error_response("Sorry no slots available at this time, please try again later");
        }

        $arrData['user_id'] = $user_id;
        $arrData['type'] = $booking_types[$type];
        $arrData['status'] = $booking_statuses['booked'];

        $book = Booking::updateOrCreate($arrData);
        if ($book) {
            // will trigger after 15 or 30 mins to check whetehr the user is check in or not if not booking will be rejected and again goes for booking
            \App\Jobs\ParkingLotAutomation::dispatch([
                'user_id' => $user_id,
                'booking_id' => $book->id
            ])->onQueue('redis')->delay(now()->addMinutes($dashboard_data['extra_time_avail_after_booking']));

            $message = "Slot is booked for parking lot {$book->parking_no} will be expired in " . $dashboard_data['extra_time_avail_after_booking'] . " minutes if you don't check in!";
            return success_response(['message' => $message]);
        }

        return error_response();
    }

    /**
     * Get available praking slots
     *
     * @param integer $is_return
     * @return mixed
     */
    public function getAvailableParkingSlots($is_return = 0)
    {
        $occupied_slots = $this->getOccupiedParkingSlots(1);
        $parking_lots = $this->getParkingLots();

        if (empty($parking_lots)) {
            return error_response('No slots availble');
        }
        $special_category_slots = !empty($occupied_slots['special_category']) ? array_diff($parking_lots['special_category'], $occupied_slots['special_category']) : $parking_lots['special_category'];
        $general_slots = !empty($occupied_slots['general']) ? array_diff($parking_lots['general'], $occupied_slots['general']) : $parking_lots['general'];

        if ($is_return) {
            return [
                'general' => $general_slots,
                'special_category' => $special_category_slots,
            ];
        }
        $data['general']['slots_available'] = !empty($general_slots) ? implode(",", $general_slots) : 0;
        $data['general']['total'] = !empty($general_slots) ? count($general_slots) : 0;
        $data['special_category']['slots_available'] = !empty($special_category_slots) ? implode(",", $special_category_slots) : 0;
        $data['special_category']['total'] = !empty($special_category_slots) ? count($special_category_slots) : 0;

        return success_response(['data' => $data]);
    }

    /**
     * Get all occupied praking slots
     *
     * @param integer $is_return
     * @return mixed
     */
    public function getOccupiedParkingSlots($is_return = 0)
    {
        $booked_slots = [];
        $occupied_slots = Booking::whereIn('status', ['booked', 'check_in'])->get(['type','parking_no'])->groupBy('type')->toArray() ?? [];
        if (!empty($occupied_slots)) {
            $booking_types = config('custom_config.BOOKING_TYPES');
            foreach ($booking_types as $key => $value) {
                $booked_slots[$key] = array_pluck($occupied_slots[$key], 'parking_no');
            }
        }
        if ($is_return) {
            return $booked_slots;
        }

        $data = [];
        $data['general']['booked_slots'] = !empty($booked_slots['general']) ? implode(",", $booked_slots['general']) : 0;
        $data['general']['total'] = !empty($booked_slots['general']) ? count($booked_slots['general']) : 0;
        $data['special_category']['booked_slots'] = !empty($booked_slots['special_category']) ? implode(",", $booked_slots['special_category']) : 0;
        $data['special_category']['total'] = !empty($booked_slots['special_category']) ? count($booked_slots['special_category']) : 0;

        return success_response(['data' => $data]);
    }

    /**
     * Get Parking Lots
     *
     * @return array
     */
    protected function getParkingLots()
    {
        $statastics = $this->getParkingDashboardData();

        /**It is not recomended we should have a separate table for parking lots with status and type
        this is just for poc **/
        $parking_lots = [];
        if (!empty($statastics['total_parking_capacity'])) {
            for ($i = 1; $i <= $statastics['total_parking_capacity']; $i++) {
                if (!empty($statastics['reserved_parking_capacity']) && $i <= $statastics['reserved_parking_capacity']) {
                    $parking_lots['special_category'][] = "P_" . $i;
                } else {
                    $parking_lots['general'][] = "P_" . $i;
                }
            }
        }
        return $parking_lots;
    }

    /**
     * Get the parking lot statastics
     *
     * @return array
     */
    protected function getParkingDashboardData(): array
    {
        $dashboard_data =  ParkingLotDashboard::first()->toArray() ?? [];
        $total_active_bookings = Booking::select(DB::Raw('count(*) as total'))->whereIn('status', ['check_in', 'booked'])->groupBy('type')->orderBy('type', 'asc')->get()->toArray() ?? [];
        // dd($total_active_bookings);

        $arrResult = [];
        $arrResult['general'] = !empty($total_active_bookings[0]['total']) ? $dashboard_data['not_reserved_parking_capacity'] - $total_active_bookings[0]['total'] : $dashboard_data['not_reserved_parking_capacity'];
        $arrResult['special_category'] = !empty($total_active_bookings[1]['total']) ? $dashboard_data['reserved_parking_capacity'] - $total_active_bookings[1]['total'] : $dashboard_data['reserved_parking_capacity'];
        $arrResult['total_parking_capacity'] = $dashboard_data['total_parking_capacity'] ?? 0;
        $arrResult['total_slots_reserved'] = !empty($total_active_bookings[0]['total']) ? ($total_active_bookings[0]['total'] + $total_active_bookings[1]['total']) : 0;
        $arrResult['extra_time_avail_after_booking'] = 30;
        if ($arrResult['total_slots_reserved'] > ($arrResult['total_parking_capacity'] - $arrResult['total_slots_reserved'])) {
            $arrResult['extra_time_avail_after_booking'] = 15; //15 mins if occupied more than 50%
        }
        $arrResult = array_merge($arrResult, $dashboard_data);
        return $arrResult;
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
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
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
