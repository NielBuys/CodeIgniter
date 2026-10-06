<?php

class DB_field_data_test extends CI_TestCase {

	/**
	 * @var object Database/Query Builder holder
	 */
	protected $db;

	public function set_up()
	{
		$this->db = Mock_Database_Schema_Skeleton::init(DB_DRIVER);
		$forge = Mock_Database_Schema_Skeleton::$forge;

		$forge->drop_table('nullability', TRUE);
		$forge->add_field(array(
			'id' => array(
				'type' => 'INTEGER',
				'constraint' => 3,
				'null' => FALSE
			),
			'required' => array(
				'type' => 'VARCHAR',
				'constraint' => 40,
				'null' => FALSE
			),
			'optional' => array(
				'type' => 'VARCHAR',
				'constraint' => 40,
				'null' => TRUE
			)
		));
		$forge->create_table('nullability') OR show_error('Unable to create the `nullability` table');

		$this->db->insert('nullability', array('id' => 1, 'required' => 'yes', 'optional' => NULL));
	}

	public function tear_down()
	{
		Mock_Database_Schema_Skeleton::$forge->drop_table('nullability', TRUE);
	}

	// ------------------------------------------------------------------------

	/**
	 * Every driver in the CI matrix reads table nullability from the schema
	 */
	public function test_table_field_data_reports_is_nullable()
	{
		$fields = $this->_by_name($this->db->field_data('nullability'));

		$this->assertSame(0, $fields['id']->is_nullable);
		$this->assertSame(0, $fields['required']->is_nullable);
		$this->assertSame(1, $fields['optional']->is_nullable);
	}

	// ------------------------------------------------------------------------

	/**
	 * Result metadata only carries nullability for MySQL; every other
	 * driver sets the documented default of 1, so the property is always there
	 */
	public function test_result_field_data_reports_is_nullable()
	{
		$fields = $this->_by_name($this->db->get('nullability')->field_data());

		$reports = ($this->db->dbdriver === 'mysqli')
			OR ($this->db->dbdriver === 'pdo' && $this->db->subdriver === 'mysql');

		$this->assertSame($reports ? 0 : 1, $fields['id']->is_nullable);
		$this->assertSame($reports ? 0 : 1, $fields['required']->is_nullable);
		$this->assertSame(1, $fields['optional']->is_nullable);
	}

	// ------------------------------------------------------------------------

	/**
	 * Index field_data() output by lower-cased column name
	 *
	 * @param	array	$field_data
	 * @return	array
	 */
	protected function _by_name($field_data)
	{
		$this->assertIsArray($field_data);

		$fields = array();
		foreach ($field_data as $field)
		{
			$fields[strtolower($field->name)] = $field;
		}

		$this->assertCount(3, $fields);
		return $fields;
	}

}
