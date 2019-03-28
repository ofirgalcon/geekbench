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
* gpu_name - string - Name of the GPU that is matched
* last_cache_pull - bigint - Timestamp of when cache files were last updated or when scores were last entered for machine
* mac_benchmarks - mediumtext - JSON string containing benchmarks for Macs
* cuda_benchmarks - mediumtext - JSON string containing CUDA benchmarks
* opencl_benchmarks - mediumtext - JSON string containing OpenCL benchmarks