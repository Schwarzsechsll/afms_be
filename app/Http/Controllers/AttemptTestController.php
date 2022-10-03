<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AttemptTest;
use App\Models\TestResult;
use App\Models\TestResultSuicidalRisk;
use Validator;
use Exception;
use Illuminate\Support\Facades\DB;

class AttemptTestController extends Controller
{
    public function store(Request $request)
    {
        // dd($request);
        $validator = Validator::make($request->all(), [
            'added_by' => 'required|integer',
            'patient_id' => 'required|integer',
            'test_name' => 'required|string',
            'test_section_name' => 'required|string',
            'result' => 'required|json',
            'user_ip_address' => 'required'
        ]);
        if ($validator->fails()) {
            return response()->json(["message" => $validator->errors(), "code" => 422]);
        }
        // dd($request);

        $result = json_decode($request->result, true);
        // dd($result);
        $addTestResult = [];
        $level = [];
        if (count($result) > 0) {
            $i = 0;
            $whoDasTotal = 0;
            foreach ($result as $key => $val) {
                foreach ($val as $kk => $vv) {
                    //  $TestResult[$request->test_name][$kk] =  $this->prepareResult($vv, $request->test_name);
                    if (
                        $request->test_name == 'cbi'
                        || $request->test_name == 'phq9'
                        || $request->test_name == 'whodas'
                        || $request->test_name == 'bdi'
                        || $request->test_name == 'bai'
                        || $request->test_name == 'psp'
                        || $request->test_name == 'atq'
                        || $request->test_name == 'si'
                    ) {
                        $testResult[$i] =
                            [
                                'added_by' =>  $request->added_by,
                                'patient_id' =>  $request->patient_id,
                                'test_name' =>  $request->test_name,
                                'ip_address' => $request->user_ip_address,
                                'created_at' =>  date('Y-m-d H:i:s'),
                                'updated_at' =>  date('Y-m-d H:i:s'),
                                'test_section_name' => $kk,
                                'result' => $this->prepareCBIResult($vv,$request->test_name)
                            ];
                        if ($request->test_name != 'bdi' && $request->test_name != 'bai' && $request->test_name != 'atq' && $request->test_name != 'psp' && $request->test_name != 'si') {
                            if ($request->test_name == 'cbi') {
                                $level[$kk] =  ['score' => $this->prepareCBIResult($vv,$request->test_name), 'level' => $this->prepareCBILevel($this->prepareCBIResult($vv,$request->test_name), $request->test_name)];
                            }else if($request->test_name == 'whodas'){
                                $level[$kk] =  ['score' => $this->prepareWHODASResult($vv), 'level' => $this->prepareCBILevel($this->prepareWHODASResult($vv), $request->test_name)];
                            } else {
                                $level[$kk] = $this->preparePHQ9Result($vv);
                            }
                        }
                        if ($request->test_name == 'phq9') {
                            $level['PHQ9Score'] = $this->getPHQ9ResultValue($vv);
                        }
                        if ($request->test_name == 'whodas') {
                            $whoDasTotal += $this->prepareCBIResult($vv,$request->test_name);
                            $level['UserTotal'] =  $whoDasTotal;
                        }
                        if ($request->test_name == 'bdi' || $request->test_name == 'bai' || $request->test_name == 'atq' || $request->test_name == 'psp' || $request->test_name == 'si') {
                            $res = $this->getBDINBAIResultValue($this->prepareCBIResult($vv,$request->test_name), $request->test_name);
                            $level[$kk] = $res[0];
                            $level[$request->test_name . 'Score'] = $res[1];
                        }
                    } else if ($request->test_name == 'dass') {
                        $testResult = $this->prepareDASSResult($vv, $request);
                        $level = $this->getDassLevel($testResult);
                    }

                    foreach ($vv as $k => $v) {
                        $addTestResult[$i] = [
                            'added_by' =>  $request->added_by,
                            'patient_mrn_id' =>  $request->patient_id,
                            'test_name' =>  $request->test_name,
                            'test_section_name' => $kk,
                            'question_id' =>  $k,
                            'answer_id' => $v,
                            'user_ip_address' => $request->user_ip_address,
                            'created_at' =>  date('Y-m-d H:i:s'),
                            'updated_at' =>  date('Y-m-d H:i:s')
                        ];
                        $i++;
                    }
                }
            }
            //  dd($testResult);
            try {
                AttemptTest::insert($addTestResult);
                TestResult::insert($testResult);
                return response()->json(["message" => "Answer submitted", "result" => $level, "code" => 200]);
            } catch (Exception $e) {
                return response()->json(["message" => $e->getMessage(), 'Exception' => $addTestResult, "code" => 200]);
            }
        }
    }

    public function prepareCBIResult($resultSet,$testName)
    {
        $result = 0;
        $values = ['1' => 100, '2' => 75, '3'=>35,'4'=>25,'5'=>0];
        $revValues = ['5' => 100, '4' => 75, '3'=>35,'2'=>25,'1'=>0];
        $valuesphq9 = ['1' => 0, '2' => 1, '3'=>2,'4'=>3];
        $i = 1;
        // dd($resultSet);
        if($testName=='cbi'){
            // dd('if');
            // dd($resultSet);
        foreach ($resultSet as $k => $v) {
            if($i<7){
            $result += $values[$v];
            }
            else{
            $result += $revValues[$v];
            }

            $i++;
        }
        return round($result/count($resultSet));
    } else if ($testName=='dass'){
           // dd('if');
           foreach ($resultSet as $k => $v) {
            if($i<7){
            $result += $values[$v];
            }
            else{
            $result += $revValues[$v];
            }

            $i++;
        }
        return round($result/count($resultSet));
    } elseif($testName=='si'){
        // dd($testName);
        $result = 0;
        $values = ['0' => 0, '1' => 1, '2'=>2];
        $i = 1;
        foreach ($resultSet as $k => $v) {
            if($i<16){
            $result += $values[$v];
            }

            $i++;
        }
        // dd($result);
        return $result;
    }
    else{
        // dd('if'.$testName);
        $result = 0;
        foreach ($resultSet as $k => $v) {
            $result += $v;
        }
        return $result;
    }
    }
    public function prepareWHODASResult($resultSet)
    {
        $result = 0;
        foreach ($resultSet as $k => $v) {
            $result += $v;
        }
        return $result;
    }
    public function prepareCBILevel($value, $test)
    {
        if ($test == 'cbi') {
            if ($value >= 0 && $value <= 49) {
                return 'Normal';
            } else if ($value >= 50 && $value <= 74) {
                return 'Moderate';
            } else if ($value >= 75 && $value <= 99) {
                return 'High Depression';
            } else {
                return 'Severe';
            }
        } else {
            if ($value >= 0 && $value <= 14) {
                return 'Normal';
            } else if ($value >= 15 && $value <= 29) {
                return 'Moderate';
            } else if ($value >= 30 && $value <= 45) {
                return 'High Depression';
            } else if ($value >= 46 && $value <= 63) {
                return 'Severe';
            }
        }
    }
    public function preparePHQ9Result($resultSet)
    {   $testName="";
        $value = $this->prepareCBIResult($resultSet,$testName);
        if ($value >= 0 && $value <= 4) {
            return 'Minimal Depression';
        } else if ($value >= 5 && $value <= 9) {
            return 'Mild Depression';
        } else if ($value >= 10 && $value <= 14) {
            return 'Moderate Depression';
        } else if ($value >= 15 && $value <= 19) {
            return 'Moderately severe depression';
        } else {
            return 'Severe Depression';
        }
    }
    public function getPHQ9ResultValue($resultSet)
    {   $testName="";
        return  $this->prepareCBIResult($resultSet,$testName);
    }
    public function prepareDASSResult($resultSet, $request)
    {
        $stress = [1, 6, 8, 11, 12, 14, 18];
        $anxiety = [2, 4, 7, 9, 15, 19, 20];
        $depression = [3, 5, 10, 13, 16, 17, 21];
        $result['stress'] = 0;
        $result['anxiety'] = 0;
        $result['depression'] = 0;
        foreach ($resultSet as $k => $v) {
            if (in_array($k, $stress)) {
                $result['stress'] += $v;
            } else if (in_array($k, $anxiety)) {
                $result['anxiety'] += $v;
            } else if (in_array($k, $depression)) {
                $result['depression'] += $v;
            }
        }

        $testResult[0] =
            [
                'added_by' =>  $request->added_by,
                'patient_id' =>  $request->patient_id,
                'test_name' =>  $request->test_name,
                'ip_address' => $request->user_ip_address,
                'created_at' =>  date('Y-m-d H:i:s'),
                'updated_at' =>  date('Y-m-d H:i:s'),
                'test_section_name' => 'Stress',
                'result' => $result['stress']
            ];
        $testResult[1] =
            [
                'added_by' =>  $request->added_by,
                'patient_id' =>  $request->patient_id,
                'test_name' =>  $request->test_name,
                'ip_address' => $request->user_ip_address,
                'created_at' =>  date('Y-m-d H:i:s'),
                'updated_at' =>  date('Y-m-d H:i:s'),
                'test_section_name' => 'Anxiety',
                'result' => $result['anxiety']
            ];
        $testResult[2] =
            [
                'added_by' =>  $request->added_by,
                'patient_id' =>  $request->patient_id,
                'test_name' =>  $request->test_name,
                'ip_address' => $request->user_ip_address,
                'created_at' =>  date('Y-m-d H:i:s'),
                'updated_at' =>  date('Y-m-d H:i:s'),
                'test_section_name' => 'Depression',
                'result' => $result['depression']
            ];
        return $testResult;
    }

    public function getDassLevel($testResult)
    {
        $result = [];
        foreach ($testResult as $k => $v) {
            $level = strtolower($v['test_section_name']);
            $value = $v['result'];
            if ($level == 'stress') {
                if ($value >= 0 && $value <= 7) {
                    $result['Stress'] = 'Normal';
                } else if ($value >= 8 && $value <= 9) {
                    $result['Stress'] = 'Mild';
                } else if ($value >= 10 && $value <= 13) {
                    $result['Stress'] = 'Moderate';
                } else if ($value >= 14 && $value <= 17) {
                    $result['Stress'] = 'Severe';
                } else {
                    $result['Stress'] = 'Extreme';
                }
                $result['Stress_Value'] = $value;
            }
            if ($level == 'depression') {
                if ($value >= 0 && $value <= 5) {
                    $result['Depression'] = 'Normal';
                } else if ($value >= 6 && $value <= 7) {
                    $result['Depression'] = 'Mild';
                } else if ($value >= 8 && $value <= 10) {
                    $result['Depression'] = 'Moderate';
                } else if ($value >= 11 && $value <= 14) {
                    $result['Depression'] = 'Severe';
                } else {
                    $result['Depression'] = 'Extreme';
                }
                $result['Depression_Value'] = $value;
            }
            if ($level == 'anxiety') {
                if ($value >= 0 && $value <= 4) {
                    $result['Anxiety'] = 'Normal';
                } else if ($value >= 5 && $value <= 6) {
                    $result['Anxiety'] = 'Mild';
                } else if ($value >= 7 && $value <= 8) {
                    $result['Anxiety'] = 'Moderate';
                } else if ($value >= 9 && $value <= 10) {
                    $result['Anxiety'] = 'Severe';
                } else {
                    $result['Anxiety'] = 'Extreme';
                }
                $result['Anxiety_Value'] = $value;
            }
        }
        return $result;
    }

    public function prepareDassLevel($value, $level)
    {
            if ($level == 'stress') {
                if ($value >= 0 && $value <= 7) {
                    return 'Normal';
                } else if ($value >= 8 && $value <= 9) {
                    return 'Mild';
                } else if ($value >= 10 && $value <= 13) {
                    return 'Moderate';
                } else if ($value >= 14 && $value <= 17) {
                    return 'Severe';
                } else {
                    return 'Extreme';
                }
                return  $value;
            }
            if ($level == 'depression') {
                if ($value >= 0 && $value <= 5) {
                   return 'Normal';
                } else if ($value >= 6 && $value <= 7) {
                   return 'Mild';
                } else if ($value >= 8 && $value <= 10) {
                   return 'Moderate';
                } else if ($value >= 11 && $value <= 14) {
                   return 'Severe';
                } else {
                   return 'Extreme';
                }
                $result['Depression_Value'] = $value;
            }
            if ($level == 'anxiety') {
                if ($value >= 0 && $value <= 4) {
                    return 'Normal';
                } else if ($value >= 5 && $value <= 6) {
                    return 'Mild';
                } else if ($value >= 7 && $value <= 8) {
                    return 'Moderate';
                } else if ($value >= 9 && $value <= 10) {
                    return 'Severe';
                } else {
                    return 'Extreme';
                }
                return $value;
            }
       
    }

    public function preparePHQ9Level($value, $level)
    {   
        if ($value >= 0 && $value <= 4) {
            return 'Minimal Depression';
        } else if ($value >= 5 && $value <= 9) {
            return 'Mild Depression';
        } else if ($value >= 10 && $value <= 14) {
            return 'Moderate Depression';
        } else if ($value >= 15 && $value <= 19) {
            return 'Moderately severe depression';
        } else {
            return 'Severe Depression';
        }
    }

    public function testHistory(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'patient_id' => 'required|integer',
        ]);
        if ($validator->fails()) {
            return response()->json(["message" => $validator->errors(), "code" => 422]);
        }
        $list1 = TestResult::select(DB::raw('SUM(result) AS result'), 'test_name','patient_id',DB::raw('group_concat(test_section_name) as test_section_name'),DB::raw('group_concat(result) as results'), 
        DB::raw("DATE_FORMAT(created_at, '%d-%m-%Y') as date")
        ,DB::raw("DATE_FORMAT(created_at, '%Y-%m-%d %H:%i:%s') as datetime"))
            ->where('patient_id', $request->patient_id)->groupBy('created_at', 'test_name','patient_id')->get();
            // dd($list1);

            $sr = TestResultSuicidalRisk::select('result','patient_id', DB::raw("'SR' as test_name"), DB::raw("DATE_FORMAT(created_at, '%d-%m-%Y') as date"),
            DB::raw("DATE_FORMAT(created_at, '%Y-%m-%d %H:%i:%s') as datetime"))
            ->where('patient_id', $request->patient_id)->get();

            $list=[];
            foreach ($list1 as $key => $val) {
                if($val['test_name'] == 'cbi'){
                    $brk = explode(',',$val['results']);
                    $levels = [];
                    foreach($brk as $v){
                    $levels[] =$this->prepareCBILevel($v,'cbi');
                    }
                    $val['levels'] = implode(',',$levels);
                    }elseif($val['test_name'] == 'bdi'){
                    $val['levels'] = $this->getBDINBAIResultValue($val['results'],'bdi');
                    }elseif($val['test_name'] == 'phq9'){
                    $val['levels'] = $this->preparePHQ9Level($val['results'],'phq9');
                    }
                    
                    elseif($val['test_name'] == 'dass'){
                    $brk = explode(',',$val['results']);
                    $brk_txt = explode(',',$val['test_section_name']);
                    $levels = [];
                    foreach($brk as $k=>$v){
                    $levels[] =$this->prepareDassLevel($v,strtolower($brk_txt[$k]));
                    }
                    $val['levels'] = implode(',',$levels);
                    }
                    elseif($val['test_name'] == 'bai'){
                    $val['levels'] = $this->getBDINBAIResultValue($val['results'],'bai');
                    }
                    elseif($val['test_name'] == 'si'){
                        $val['levels'] = $this->getBDINBAIResultValue($val['results'],'si');
                        }
                $list[] = $val;
            }
            
            foreach ($sr as $key => $val) {
                $list[] = $val;
            }
        return response()->json(["message" => "Test List.", 'list' => $list, "code" => 200]);
    }

    public function testHistoryResultShow(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'patient_id' => 'required|integer',
            'type' =>''
        ]);
        if ($validator->fails()) {
            return response()->json(["message" => $validator->errors(), "code" => 422]);
        }
        if($request->type=="atq"){
            $list1 = DB::table('attempt_test')
            ->join('patient_cbi_onlinetest', 'patient_cbi_onlinetest.id', '=', 'attempt_test.question_id')
            ->select('*')
            ->where('attempt_test.patient_mrn_id', $request->patient_id)->where('attempt_test.created_at', $request->datetime)
            ->where('attempt_test.test_name', $request->type)
            ->get();
            
            $list=[];
            foreach ($list1 as $key => $val) {
                $tmp1 =(array) $val;
                for ($iii=0; $iii <6; $iii++) { 
                    $tmp1["Answer$iii"] = array('value' => ($val->answer_id==$iii)? 'true':'false','text'=> $tmp1["Answer$iii"]);
                }
                $list[] =  $tmp1;
            }
        return response()->json(["message" => "Test List.", 'list' => $list, "code" => 200]);

        }elseif($request->type=="psp"){
            $list1 = DB::table('attempt_test')
            ->join('patient_cbi_onlinetest', 'patient_cbi_onlinetest.id', '=', 'attempt_test.question_id')
            ->select('*')
            ->where('attempt_test.patient_mrn_id', $request->patient_id)->where('attempt_test.created_at', $request->datetime)
            ->where('attempt_test.test_name', $request->type)
            ->get();

            $list=[];
            foreach ($list1 as $key => $val) {
                $tmp1 =(array) $val;
                for ($iii=0; $iii <6; $iii++) { 
                    $tmp1["Answer$iii"] = array('value' => ($val->answer_id==$iii)? 'true':'false','text'=> $tmp1["Answer$iii"]);
                }
                $list[] =  $tmp1;
            }
        return response()->json(["message" => "Test List.", 'list' => $list, "code" => 200]);
        }elseif($request->type=="laser"){
            $list1 = TestResult::select('*')->where('created_at',"=",$request->datetime)
            ->where('patient_id',"=",$request->patient_id)->get();
        return response()->json(["message" => "Test List laser.", 'list' => $list1, "code" => 200]);
        }else{
            $list = TestResultSuicidalRisk::select('result', DB::raw("'SR' as test_name"), DB::raw("DATE_FORMAT(created_at, '%d-%m-%Y') as date"))
            ->where('patient_id', $request->patient_id)->get();
        return response()->json(["message" => "Test List.", 'list' => $list, "code" => 200]);
        }

            // $sr = TestResultSuicidalRisk::select('result', DB::raw("'SR' as test_name"), DB::raw("DATE_FORMAT(created_at, '%d-%m-%Y') as date"),'id')
            // ->where('patient_id', $request->patient_id)->get();

        //     $list=[];
        //     foreach ($list1 as $key => $val) {
        //         $tmp1 =(array) $val;
        //         for ($iii=0; $iii <6; $iii++) { 
        //             $tmp1["Answer$iii"] = array('value' => ($val->answer_id==$iii)? 'true':'false','text'=> $tmp1["Answer$iii"]);
        //         }
        //         $list[] =  $tmp1;
        //     }
        // return response()->json(["message" => "Test List.", 'list' => $list, "code" => 200]);
    }

    public function getBDINBAIResultValue($value, $testName)
    {
        if ($testName == 'bdi') {
            if ($value >= 1 && $value <= 10) {
                return ['Normal', '1-10'];
            } else if ($value >= 11 && $value <= 16) {
                return ['Mild mood disturbance', '11-16'];
            } else if ($value >= 17 && $value <= 20) {
                return ['Borderline clinical depression', '17-20'];
            } else if ($value >= 21 && $value <= 30) {
                return ['Moderate depression', '21-30'];
            } else if ($value >= 31 && $value <= 40) {
                return ['Severe depression', '31-40'];
            } else {
                return ['Extreme depression', '>40'];
            }
        }
        if ($testName == 'bai') {
            if ($value >= 0 && $value <= 21) {
                return ['Low anxiety', '0-21'];
            } else if ($value >= 22 && $value <= 35) {
                return ['Moderate anxiety', '22-35'];
            } else {
                return ['Potentially concerning levels of anxiety', '>35'];
            }
        }
        if ($testName == 'atq') {
            if ($value >= 0 && $value <= 17) {
                return ['Low', '0-27'];
            } else if ($value >= 18 && $value <= 51) {
                return ['Moderate', '18-51'];
            } else {
                return ['High', '>51'];
            }
        }
        if ($testName == 'psp') {
            if ($value >= 0 && $value <= 2) {
                return ['Absent', '0-2'];
            } else if ($value >= 2 && $value <= 5) {
                return ['Mild', '2-5'];
            } else if ($value >= 6 && $value <= 8) {
                return ['Manifest', '6-8'];
            } else if ($value >= 9 && $value <= 11) {
                return ['Marked', '9-11'];
            } else if ($value >= 12 && $value <= 15) {
                return ['Severe', '12-15'];
            } else {
                return ['Very Severe', '>15'];
            }
        }
        if ($testName == 'si') {
            if ($value >= 0 && $value <= 10) {
                return ['Low Intent', '0-10'];
            } else if ($value > 10 && $value <= 20) {
                return ['Medium Intent', '11-20'];
            } else {
                return ['High Intent', '21+'];
            }
        }
    }
    public function resultdetail(Request $request)
    {
        $value=TestResult::select('result')
        ->where('id', '=', $request->id)
        ->where('test_name', '=', $request->test_name)
        ->get();
        // dd($value[0]['result']);
        if ($request->test_name == 'atq') {
            if ($value[0]['result'] >= 0 && $value[0]['result'] <= 17) {
                return response()->json(["message" => "Low', 0-27", 'result'=>$value[0]['result'], "code" => 200]);
                // return ['Low', '0-27'];
            } else if ($value[0]['result'] >= 18 && $value[0]['result'] <= 51) {
                return response()->json(["message" => "Moderate', 18-51", 'result'=>$value[0]['result'], "code" => 200]);
                // return ['Moderate', '18-51'];
            } else {
                return response()->json(["message" => "High, >51", 'result'=>$value[0]['result'], "code" => 200]);
                // return ['High', '>51'];
            }
        }
        if ($request->test_name == 'psp') {
            if ($value[0]['result'] >= 0 && $value[0]['result'] <= 2) {
                return response()->json(["message" => "Absent, 0-2", 'result'=>$value[0]['result'], "code" => 200]);
                // return ['Absent', '0-2'];
            } else if ($value[0]['result'] >= 2 && $value[0]['result'] <= 5) {
                return response()->json(["message" => "Mild, 2-5", 'result'=>$value[0]['result'], "code" => 200]);
                // return ['Mild', '2-5'];
            } else if ($value[0]['result'] >= 6 && $value[0]['result'] <= 8) {
                return response()->json(["message" => "Manifest, 6-8", 'result'=>$value[0]['result'], "code" => 200]);
                // return ['Manifest', '6-8'];
            } else if ($value[0]['result'] >= 9 && $value[0]['result'] <= 11) {
                return response()->json(["message" => "Marked, 9-11", 'result'=>$value[0]['result'], "code" => 200]);
                // return ['Marked', '9-11'];
            } else if ($value[0]['result'] >= 12 && $value[0]['result'] <= 15) {
                return response()->json(["message" => "Severe, 12-15", 'result'=>$value[0]['result'], "code" => 200]);
                // return ['Severe', '12-15'];
            } else {
                return response()->json(["message" => "Very Severe, >15", 'result'=>$value[0]['result'], "code" => 200]);
                // return ['Very Severe', '>15'];
            }
        }
        if ($request->test_name == 'si') {
            if ($value[0]['result'] >= 15 && $value[0]['result'] <= 19) {
                return response()->json(["message" => "Low Intent, 15-19", 'result'=>$value[0]['result'], "code" => 200]);
                // return ['Low Intent', '15-19'];
            } else if ($value[0]['result'] >= 20 && $value[0]['result'] <= 28) {
                return response()->json(["message" => "Medium Intent, 20-28", 'result'=>$value[0]['result'], "code" => 200]);
                // return ['Medium Intent', '20-28'];
            } else {
                return response()->json(["message" => "High Intent, >28", 'result'=>$value[0]['result'], "code" => 200]);
                // return ['High Intent', '>28'];
            }
        }
    }
}
