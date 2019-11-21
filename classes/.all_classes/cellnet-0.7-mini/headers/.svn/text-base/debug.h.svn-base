#ifndef __DEBUG_H__
#define __DEBUG_H__

#include <stdio.h>
#include "logger.h"

#ifdef _DEBUG_THREADS_
    #define _LOG_THREAD_IDS_
#endif

#ifdef _LOG_THREAD_IDS_
    #define __LOG_THREAD_STR__ " [%p] "
    #define __LOG_THREAD_PARAM__ getpid(),
#else
    #define __LOG_THREAD_STR__ 
    #define __LOG_THREAD_PARAM__ 
#endif

#define __LOG_MESSAGE__(catagory, type, format, ...) __LOG_MESSAGE_TO__(logfile, catagory, type, format, __VA_ARGS__)

#define __LOG_MESSAGE_TO__(fileh, catagory, type, format, ...) \
  do { \
    if (!fileh) fileh = stderr; \
    else { \
      fprintf(\
          fileh, \
          catagory ": " type "\t" __LOG_THREAD_STR__ " (" __FILE__ ":%u):\t" format "\n", \
          __LOG_THREAD_PARAM__ \
          __LINE__, \
          __VA_ARGS__ \
      ); \
      fflush(logfile); \
    }   \
  } while(0)


#ifdef _SHOW_ERRORS_
    #define _show_error(type, format, ...) do { \
        __LOG_MESSAGE__("ERROR", type, format, __VA_ARGS__); \
        if (logfile != stderr && logfile != stdout) \
        __LOG_MESSAGE_TO__(stderr, "ERROR", type, format, __VA_ARGS__); \
    } while (0)
                                            
#else
    #define _show_error(...) do { } while (0)
#endif

#ifdef _SHOW_WARNINGS_
    #define _show_warning(type, format, ...) do {\
        __LOG_MESSAGE__("WARNING", type, format, __VA_ARGS__); \
        if (logfile != stderr && logfile != stdout) \
            __LOG_MESSAGE_TO__(stderr, "WARNING", type, format, __VA_ARGS__); \
    } while(0)
#else
    #define _show_warning(...) do { } while (0)
#endif


#ifdef _DEBUGGING_
    #include <stdio.h>

    #ifdef _DEBUG_ALL_
        #define _DEBUG_FLOW_
        #define _DEBUG_MORE_FLOW_
        #define _DEBUG_POINTERS_
        #define _DEBUG_LOCKS_
        #define _DEBUG_FAILURES_
        #define _DEBUG_VERBOSE_
    #endif
    #define __DEBUGGER__(type, format, ...) __LOG_MESSAGE__("DEBUG", type, format, __VA_ARGS__)
#else
    #define __DEBUGGER__(...) do { } while (0)
#endif

#define _debug(...) __DEBUGGER__(__VA_ARGS__)
#define _debugger(...) _debug(__VA_ARGS__)
    
#define _debug_flow(format, ...) __DEBUGGER__("Flow", format, __VA_ARGS__)
#define _debug_more_flow(...) _debug_flow(__VA_ARGS__)
#define _debug_pointers(format, ...) __DEBUGGER__("Pointers", format, __VA_ARGS__)
#define _debug_locks(format, ...) __DEBUGGER__("Locks", format, __VA_ARGS__)
#define _debug_failure(format, ...) __DEBUGGER__("Failure", format, __VA_ARGS__)
#define _debug_verbose(format, ...) __DEBUGGER__("Verbose", format, __VA_ARGS__)

#endif
