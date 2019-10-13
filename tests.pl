#!/usr/bin/env perl
# tests.pl: A script to test the Guard server's HTTP API. Run mysql-test-setup.sql first. MySQL and
# Apache2 must be running.
#
# (c) 2019 thatlittlegit. Licensed under the Apache license, version 2.0.
use HTTP::Tiny;
use DBI;
use Test::More tests => 1;
use JSON;

$handle = DBI->connect("dbi:mysql:guardtests@localhost", "tester", "password");
$handle->do("DROP TABLE IF EXISTS `table`");
$handle->do("CREATE TABLE `table` (`id` INT NOT NULL AUTO_INCREMENT, `name` VARCHAR(50) NOT NULL, `job` VARCHAR(25) NOT NULL, `age` TINYINT NOT NULL, PRIMARY KEY (`id`))");

subtest '"/" endpoint', sub {
	$req = HTTP::Tiny->new->get('http://tester:password@guard.localhost/guard/?__database=guardtests');
	ok($req->{success}, "Response succeeds");
	ok(defined(decode_json($req->{content})->{"note"}), "The 'note' field is not empty");
}
