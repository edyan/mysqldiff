# Improvements
* Add an option to copy the left database info to the right (copy to right)
* Allow another charset
* Add Multilingual support
* Add logs
* PhpUnit

# Doctrine limitations
As Doctrine is limited in some comparisons, I have to find another way for :
* Detects changes in Table Charset + Comments + Engine and other properties
* For doctrine MEDIUMINT = INT, it doesn't detect some changes when our schmeas aren't build with it
