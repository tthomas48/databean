<?php

namespace BuyPlayTix\DataBean;

use PHPUnit\Framework\TestCase;
use Pseudo\Pdo;

class DBAdapterTest extends TestCase {

  public function testLoad() {
    DB::setInstance(new MockDB());
    $db = DB::getInstance();

    $db->getPDO()->mock("select * from My_Table where UID = ?", []);
    $databean = new TestDataBean();
    $this->assertEquals("where UID = ?", $databean->getWhereClause());
    $this->assertEquals([$databean->fields[$databean->getPk()]], $databean->getWhereParams());

    $db->getPDO()->mock("select * from My_Table where UID = ?", [], ["abcefg"]);
    $databean = new TestDataBean("abcefg");
    $this->assertEquals("where UID = ?",   $databean->getWhereClause());
    $this->assertEquals([$databean->fields[$databean->getPk()]], $databean->getWhereParams());

    $db->getPDO()->mock("select * from My_Table where UID = ?", [['UID' => 'abcefg']], ['abcefg']);
    $databean = new TestDataBean("abcefg");
    $this->assertEquals("where UID = ?", $databean->getWhereClause());
    $this->assertEquals(['abcefg'], $databean->getWhereParams());

    $db->getPDO()->mock("select * from My_Table where NAME = ?", [], ['A']);
    $databean = new TestDataBean(["NAME", "A"]);
    $this->assertEquals("where UID = ?",   $databean->getWhereClause());
    $this->assertEquals([$databean->fields[$databean->getPk()]], $databean->getWhereParams());

    $db->getPDO()->mock("select * from My_Table where NAME = ?", [["UID" => "def", "NAME" => "A"]], ['A']);
    $databean = new TestDataBean(["NAME", "A"]);
    $this->assertEquals("where UID = ?", $databean->getWhereClause());
    $this->assertEquals(['def'], $databean->getWhereParams());
  }

  public function testLoadAll()
  {
    DB::setInstance(new MockDB());
    $db = DB::getInstance();

    $db->getPDO()->mock("select * from My_Table where UID = ?", [["UID" => "ABC", "NAME" => "A"]], ["ABC"]);
    $db->getPDO()->mock("select * from My_Table where UID = ?", [["UID" => "DEF", "NAME" => "B"]], ["DEF"]);

    $db->getPDO()->mock("select * from My_Table where NAME = ?", [], ['A']);
    $result = TestDataBean::getObjects("NAME", ["A"]);
    $this->assertEquals([], $result);

    $db->getPDO()->mock("select * from My_Table where NAME = ?", [["UID" => "ABC", "NAME" => "A"]], ['A']);
    $result = TestDataBean::getObjects("NAME", ["A"]);
    $expected = new TestDataBean("ABC");
    $this->assertEquals([$expected], $result);

    $db->getPDO()->mock("select * from My_Table where NAME = ? order by NAME", [["UID" => "ABC", "NAME" => "A"]], ['A']);
    $result = TestDataBean::getObjects("NAME", ["A"], " order by NAME ");
    $expected = new TestDataBean("ABC");
    $this->assertEquals([$expected], $result);

    $db->getPDO()->mock("select * from My_Table where NAME in (?,?) order by NAME", [["UID" => "ABC", "NAME" => "A"], ["UID" => "DEF", "NAME" => "B"]], ['A', 'B']);
    $result = TestDataBean::getObjects("NAME", ["A", "B"], " order by NAME ");
    $expected2 = new TestDataBean("DEF");
    $this->assertEquals([$expected, $expected2], $result);

    // TODO: Add the ability to bind params in the and clause, perhaps with an array?
    $db->getPDO()->mock("select * from My_Table where NAME in (?,?) and OWNER_UID = ?", [["UID" => "ABC", "NAME" => "A"], ["UID" => "DEF", "NAME" => "B"]], ['A', 'B', 'C']);
    $result = TestDataBean::getObjects("NAME", ["A", "B"], [" and OWNER_UID = ? ", "C"]);
    $expected2 = new TestDataBean("DEF");
    $this->assertEquals([$expected, $expected2], $result);
  }

  public function testUpdate()
  {
    DB::setInstance(new MockDB());
    $db = DB::getInstance();

    $db->getPDO()->mock("select * from My_Table where UID = ?", [["UID" => "ABC", "NAME" => "A"]], ["ABC"]);
    $db->getPDO()->mock("update My_Table set `UID` = ?,`NAME` = ? where UID = ?", [["UID" => "ABC", "NAME" => "A"]], ["ABC", "Something Else", "ABC"]);
    // update
    $databean = new TestDataBean("ABC");
    $databean->NAME = 'Something Else';
    $this->assertTrue($databean->update());

    // insert
    $db->getPDO()->mock("select * from My_Table where UID = ?", [], ["DEF"]);
    $db->getPDO()->mock("insert into My_Table (`UID`,`NAME`) values (?,?)", [["UID" => "ABC", "NAME" => "A"]], ["DEF", "My Name"]);
    $databean = new TestDataBean();
    $databean->UID = "DEF";
    $databean->NAME = 'My Name';
    $this->assertTrue($databean->update());
  }

  public function testDelete()
  {
    DB::setInstance(new MockDB());
    $db = DB::getInstance();

    $db->getPDO()->mock("select * from My_Table where UID = ?", [["UID" => "ABC", "NAME" => "A"]], ["ABC"]);
    $db->getPDO()->mock("delete from My_Table where UID = ?", [], ["ABC"]);

    $databean = new TestDataBean("ABC");
    $this->assertEquals(null, $databean->delete());
  }
}

class MockDB extends DB {
  public function __construct()
  {
    $this->connect();
  }

  function connect()
  {
    $this->database = new QuotedPdo();
  }
}

class QuotedPdo extends \Pseudo\Pdo {
  public function quote($string, $parameter_type = PDO::PARAM_STR) {
    return "'" . $string . "'";
  }
}

class TestDataBean extends DataBean {
  public $pk = "UID";

  public $table = "My_Table";

}