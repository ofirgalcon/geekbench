Geekbench module
==========

Reports the Geekbench stats for the computer. 

Client will trigger server to lookup Geekbench information from Geekbench's API once a week.


Table Schema
---
* id - Unique ID
* serial_number - Machine's serial number
* score - int - Single CPU score
* multiscore - int - Multi CPU score
* model_name - string - Geekbench's model name
* description - string - Geekbench's CPU name
* samples - int - Number of samples in Geekbench
* cuda_score - bigint - Score for CUDA (Nvida GPUs only)
* cuda_samples - bigint - How many samples are in Geekbench
* opencl_score - bigint - Score for OpenCL
* opencl_samples - bigint - How many samples are in Geekbench
* metal_score - bigint - Score for Metal
* metal_samples - bigint - How many samples are in Geekbench
* gpu_name - string - Name of the GPU that is matched
* last_run - bigint - Timestamp of when scores were last processed for machine

<img width="643" alt="image" src="https://user-images.githubusercontent.com/16665880/167967529-bcdbe263-d9b0-4392-8921-ad1893094025.png">
