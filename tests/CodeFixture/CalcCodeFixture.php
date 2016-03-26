<?php
/**
 * Copyright 2015 - 2016, Cake Development Corporation (http://cakedc.com)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright 2015 - 2016, Cake Development Corporation (http://cakedc.com)
 * @license MIT License (http://www.opensource.org/licenses/mit-license.php)
 */

namespace CakeDC\OracleDriver\Test\CodeFixture;

use CakeDC\OracleDriver\TestSuite\Fixture\MethodTestFixture;

class CalcCodeFixture extends MethodTestFixture
{
//    public $type = 'package';

    public $name = 'CALC';

    public $create = [];

    public $drop = 'drop package calc';

    public function __construct()
    {
        $this->create[] =
                "create or replace package calc is

                		function sum(a number, b number) return number;
                		function twice(a in number, b out number) return varchar2;

                end calc;";
            $this->create[] =
                "
                create or replace package body calc is

                	function sum(a number, b number) return number is
                      begin
                        return a+b;
                      end;
                    
                      function twice(a in number, b out number) return varchar2 is
                      begin
                        b := a*2;
                        return 'OK';
                      end;

                end calc;";

        parent::__construct();
    }

}
