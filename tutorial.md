# Tutorial / Documentation

## Abstract

The dataobjects module provides classes to deal with database data rows in an object oriented manner. This module
is usually installed automatically when you follow the installation instructions in the
[installer module](https://github.com/buzanits/booosta-installer). The usage of the methods provided by this module
makes it unnecessary to use SQL queries directly to retrieve or manipulate data in the database.

Booosta does not follow a strict MVC paradigma to keep ease and flexibility. But you can consider the data objects
as what is the __model__ in MVC. The cool thing is, you do not have to implement this model yourself! The classes
that the data objects are based on, are assembled automatically from the database structure. Just use them!

## Usage

### Record creation

```
# to create a new (and empty) data object, use makeDataobject($tablename)
$obj = $this->makeDataobject('lecturer');

# then add data with set($fieldname, $value)
$obj->set('name', 'Alice');
$obj->set('birthdate', '1972-09-10');
# ...

# finally insert the new record into the database
$id = $obj->insert();
```
When you set a value to `null`, `insert()` will store a `NULL` value in the database. If the according field is 
`NOT NULL`, an error will be thrown. If your table has an auto increment field (what is strongly adviceable when
working with Booosta), `insert()` returns the value that has been inserted in this field.

### Record update
```
# to update an existing record, use getDataobject($tablename, $identifier, $create = false)
$obj = $this->getDataobject('lecturer', 1);
# or
$obj = $this->getDataobject('lecturer', "name='Alice'");

# then manipulate the data as before
$obj->set('birthdate', '1972-09-23');

# finally update the record in the database
$error = $obj->update();
```
As you see in the example, the `$identifier` can be the value of the primary key (usually `id`) or a SQL clause.
This clause must be written in that manner, that it would be a valid statement in `select * from table where $clause`.
If you use a clause that returns more than one row, the first returned row is used. If you use a clause or an ID 
that returns no rows, no valid object is returned by `getDataobject()`.

You can access the data with the method `get($fieldname)`

```
# test, if a valid data object has been returned
$obj = $this->getDataobject('lecturer', "name='Joe'");
if(!$this->testDataobject($obj)) print "Dataobject not found!";

$name = $obj->get('name');

$obj = $this->getDataobject('lecturer', "name='Jane'");
$this->chkDataobject($obj, "Dataobject not found");  // raises an Error inside a `Webapp` object.

$name = $obj->get('name');
```
If you don't know if a particular record exists, you can fetch it with setting the parameter `$create` to true.
Then the record is fetched from the database if it exists, otherwise a new empty object is created. Because you
don't know if you have to insert or update the row then, there is the method `save()` which inserts a new record
or updates an existing. It always returns the ID of the record.
```
$obj = $this->getDataobject('lecturer', "name='Alice'", true);
$obj->set('name', 'Alice');
# ...
$id = $obj->save();
```
To retrieve several records from a table, you can use the method `getDataobjects($table, $clause = null, $order = 1, $limit = null)`
Again you can pass a valid SQL where clause for `$clause`, a valid `order by` clause for `$order` and a valid `limit` clause
for `$limit`.
```
$objects = $this->getDataobjects('lecturer', "birthdate>'1999-01-01'");
foreach($objects as $obj) {
  print $obj->get('name') . ' is a young lecturer.' . PHP_EOL;
}
```

### Record deletion
```
# to delete a record, retrieve it with `getDataobject()` and use the `delete()` method
$obj = $this->getDataobject('lecturer', "name='Alice'");
if($this->testDataobject($obj)) $obj->delete();
```

### Misc. methods
```
# to get an array of all columns of a record, use `get_data()`
$obj = $this->getDataobject('lecturer', "name='Alice'");
$data = $obj->get_data();

# if you have an array with the data for a record (indexed with the column names), you can pass this data
# to a data object with `set_data($data, $empty2null = false)`. If `$empty2null` is set to true, an empty string
# in the value will be interpreted as NULL.

$obj = $this->makeDataobject('lecturer');
$obj->set_data($mydata, true);
$obj->insert();

# if you have a data object with current data and want to replace one or more fields, you can use `merge_data($data, $empty2null = false)`

$obj = $this->getDataobject('lecturer', "name='Alice'");
$mydata = ['birthdate' => '1972-08-23', 'comment' => 'Maths lecturer'];
$obj->merge_data($mydata, $true);
$obj->update();
```

### Obtaining data objects in the Webapp module
In the `Webapp` module there are some methods to receive a data object with the data of the __current__ record worked on.
```
# in hook methods like `before_edit_()` or `after_action_new()` you can get a data object with the current data
protected function before_action_edit() {
  $obj = $this->get_dbobject();  # get a data object with current data
  $obj1 = $this->get_dbobject(1);  # get data object with data from row with ID 1

  do_something_with($obj, $obj1);
}
```

