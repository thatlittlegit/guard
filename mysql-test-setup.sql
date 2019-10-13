-- mysql-test-setup.sql: Create a new SQL database and user for tests.pl to use.
-- This way, this script might get high privileges and tests.pl will get lower privileges.
--
----- -- -- -[Notice]- -- -- -- --
----- This script will delete the guardtests database.
----- -- -- -[ecitoN]- -- -- -- --
--
-- (c) 2019 thatlittlegit. This code is licensed under the Apache License, version 2.0. See the
-- LICENSE file of this project.

-- Start a new transaction. If anything fails, undo everything.
START TRANSACTION;

-- Warn the user. Honestly, if they see this it's probably too late, but it might help.
SELECT "This script will create a new database and user for said database. This is for use with tests.pl!"
AS     " --- N O T I C E --- ";

-- Create a new database 'guardtests'
DROP DATABASE IF EXISTS guardtests;
CREATE DATABASE guardtests;

-- If the user tester@localhost exists, delete it. Otherwise, make it.
GRANT ALL PRIVILEGES ON *.* TO 'tester'@'localhost' IDENTIFIED BY 'password';

-- Make sure it takes effect.
FLUSH PRIVILEGES;

-- Commit the changes.
COMMIT;