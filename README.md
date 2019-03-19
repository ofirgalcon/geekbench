GeekBench module
==========

Reports the GeekBench stats for the computer. 

Client will trigger server to lookup GeekBench information from GeekBench's API once a week.

Table Schema
---
* id - Unique ID
* serial_number - Machine's serial number
* score - int - Single CPU score
* multiscore - int - Multi CPU score
* model_name - string - GeekBench's model name
* description - string - GeekBench's CPU name
* samples - int - Number of samples in GeekBench
