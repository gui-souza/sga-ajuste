<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;
use League\Csv\Reader;
use App\Setting;
use Carbon\Carbon;

class Student extends Authenticatable
{
    protected $guard = 'student';

    protected $guarded = [];


    //student->certificates > [c1, c2, ...]
    public function certificates() {
    	return $this->hasMany('App\Certificate');
    }

    //student->adjustments
    public function adjustments() {
        return $this->hasMany('App\Adjustment');
    }

    public static function findStudent($input) {
    	return Student::where('cpf', $input)
    		->orWhere('matricula', $input)
            ->orWhere('email', $input)
            ->get();
    }
    
    public static function merge($csv) {
        //reads and parses csv supplied...
        $acceptedHeader = ['matricula','cpf', 'nome', 'cr', 'chc', 'cha', 'cht', 'curriculo'];

        $reader = Reader::createFromString($csv);
        $reader->setHeaderOffset(0);
        $header = $reader->getHeader();
        $csv = null;
        $csv = [
            'header' => $header,
            'acceptedHeader' => $acceptedHeader,
        ];

        //Unprocessable csv
        if(count(array_diff($header, $acceptedHeader)) > 0) {
            //dd(count(array_diff($header, $acceptedHeader)));
            $csv['code'] = 422;
            return $csv;
        }


        $records = $reader->getRecords();
        $insertions = $updates = 0;
        foreach ($records as $record) {
            $exists = Student::where('matricula', $record['matricula'])->count();
            if(!$exists) {
                //some hardcoding that should be temporary...
                $record += [
                    'localidade' => 'Niterói',
                    'curso' => 'ADMINISTRAÇÃO',
                ];
                $student = Student::create($record);
                $insertions++;
            } else {
                //dd($record);
                Student::where('matricula', $record['matricula'])
                    ->update($record);
                $updates++;
            }
        }
        //register setting timestamp
        Setting::updateOrInsert(['nome' => 'data_merged'], 
            [   
                'grupo' => 'estudantes', 
                'valor' => Carbon::now()->toDateTimeString(),
            ]
        );

        $csv += [
            'code' => 200,
            'numRows' => count($reader),
            'insertions' => $insertions,
            'updates' => $updates,
            'updated_at' => Carbon::now()->toDateTimeString(),
        ];
        return $csv; // < test
    }

}
