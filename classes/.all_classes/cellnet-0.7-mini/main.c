#define __MAIN_C__

#include <stdint.h>
#include <stdlib.h>
#include <stdio.h>

#include "module.h"
#include "params.h"
#include "feedscan.h"
#include "main.h"
#include "posix_shmseg.h"
#include "debug.h"

#include <sys/stat.h>
#include <sys/types.h>
#include <dirent.h>

int cellnet_init();
int cellnet_main();

extern int32_t frame_rate;
extern int32_t generate_bmps;

extern int32_t DIFF_FLOOR;
extern int32_t LUM_FLOOR_MULTIPLIER;
extern double CELL_SHARP_FLOOR;
extern double CELL_EDGE_FLOOR;
extern int32_t ZONE_MIN_MULTI;
extern const char *input_filename;
extern int32_t MIN_ADJ_EDGES;
extern int32_t SUB_ADJ_EDGES;

const char /**SIGNAL_PATH,*/ *ZONE_PATH;

static int verify_directory (const char *path);
/********************
 * global variables
 *
 */

/*******************************************************************************
 * main:
 *     Process entry point.  Parses command line options, initializes the shared
 *     memory segment, initializes the detection engine, and triggers it each
 *     time the frame's index has changed.  In this time if the segment becomes
 *     invalid, it detaches and attempts to reattach it until the writer has
 *     come back online.
 */

extern const char *_MODULE_PATH;
const char *module_name = _DEFAULT_MODULE_NAME_;
const char *log_filename;

static int verify_directory (const char *path) {
    struct stat stbuf;
    if (stat(path, &stbuf)) return -1;
    DIR *lwd = opendir(".");
    if (chdir(path)) return -1;
    if (lwd == NULL) return 0;
    fchdir(dirfd(lwd));
    closedir(lwd);
    return 0;
}

int main (int argc, char **argv) {

    /* Make stdout/stderr line (not block) buffered to see logs immediately */
    setvbuf(stdout, (char *)NULL, _IOLBF, 0);
    setvbuf(stderr, (char *)NULL, _IOLBF, 0);

    /* set default configuration values,
     * see values in headers/conf.h 
     */
    log_filename   = DEF_LOG_FILENAME;
    input_filename = DEF_INPUT;
    ZONE_PATH      = DEF_ZONE_PATH;

    LUM_FLOOR_MULTIPLIER = DEF_LUM_MULTI;
    CELL_EDGE_FLOOR      = DEF_EDGE_FLOOR;
    CELL_SHARP_FLOOR     = DEF_SHARP_FLOOR;
    DIFF_FLOOR           = DEF_DIFF_FLOOR;

    ZONE_MIN_MULTI = DEF_ZONE_MIN_MULTI;
    MIN_ADJ_EDGES  = DEF_MIN_ADJ_EDGES;
    SUB_ADJ_EDGES  = DEF_SUB_ADJ_EDGES;

    frame_rate    = DEF_RATE;
    generate_bmps = DEF_GEN_BMPS;


    /*************************************
     * define command line / environment
     * argument specifications
     */
    argspec_t args[] = {
      {
        .name  = "Input",
        .desc  = "Input filename containing YUV444 data",
        .longopt= "input",
        .opt   = 'i',
        .type  = STRING,
        .flags = ARG_REQ | ARG_PARAM_REQ | ARG_DEFAULT | ARG_PARAM | ARG_ICASE,
        .value = { .STRING = &input_filename }
      },
      {
        .name   =   "Logfile",
        .desc   =   "Log filename",
        .longopt=   "logfile",
        .opt    =   'l',
        .type   =   STRING,
        .flags  =   ARG_PARAM | ARG_PARAM_REQ | ARG_DEFAULT | ARG_ICASE,
        .value  =   { .STRING = &log_filename }
      },
      {
        .name  = "LumMul",
        .desc  = "Luminance floor multiplier",
        .type  = INT,
        .flags = ARG_PARAM | ARG_PARAM_REQ | ARG_DEFAULT | ARG_ICASE,
        .longopt= "lummul",
        .opt   = 'L',
        .value = { .INT = &LUM_FLOOR_MULTIPLIER }
      },
      {
        .name  = "EdgeFloor",
        .desc  = "Minimum clipping of cell edges",
        .type  = FLOAT,
        .flags = ARG_PARAM | ARG_PARAM_REQ | ARG_DEFAULT | ARG_ICASE,
        .longopt= "edgefloor",
        .opt   = 'E',
        .value = { .FLOAT = &CELL_EDGE_FLOOR }
      },
      {
        .name  = "SharpFloor",
        .desc  = "Minimum clipping of cell sharpness",
        .type  = FLOAT,
        .flags = ARG_PARAM | ARG_PARAM_REQ | ARG_DEFAULT | ARG_ICASE,
        .longopt= "sharpfloor",
        .opt   = 'F',
        .value = { .FLOAT = &CELL_SHARP_FLOOR }
      },
      {
        .name  = "DiffFloor",
        .desc  = "Minimum clipping for raw pixel changes",
        .type  = INT,
        .flags = ARG_PARAM | ARG_PARAM_REQ | ARG_DEFAULT | ARG_ICASE,
        .longopt= "difffloor",
        .opt   = 'f',
        .value = { .INT = &DIFF_FLOOR }
      },
      {
        .name  = "Rate",
        .desc  = "Frames per second, Rate at which the input is rescanned",
        .longopt= "rate",
        .opt   = 'r',
        .type  = INT,
        .flags = ARG_PARAM | ARG_PARAM_REQ | ARG_DEFAULT | ARG_ICASE,
        .value = { .INT = &frame_rate }
      },
      {
        .name  = "Bmp",
        .desc  = "Generate bmp of edges",
        .longopt= "bmp",
        .opt   = 'b',
        .type  = SWITCH,
        .value = { .INT = &generate_bmps }
      },
      {
        .name  = "ZoneMinMulti",
        .longopt= "zoneminmulti",
        .desc  = "Zone requires X active pixels where X == log(total_pixels>>4) * zoneminmulti",
        .type  = INT,
        .flags = ARG_PARAM | ARG_PARAM_REQ | ARG_DEFAULT | ARG_ICASE,
        .opt   = 'Z',
        .value = { .INT = &ZONE_MIN_MULTI } 
      },
      {
        .name  = "MinAdjEdges",
        .longopt= "minadjedges",
        .desc  = "Minimum adjacent edges",
        .type  = INT,
        .flags = ARG_PARAM | ARG_PARAM_REQ | ARG_DEFAULT | ARG_ICASE,
        .opt   = 'E',
        .value = { .INT = &MIN_ADJ_EDGES }
      },
      {
        .name  = "SubAdjEdges",
        .longopt= "subadjedges",
        .desc  = "Subtract adjacent edges before multiplying delta",
        .type  = INT,
        .flags = ARG_PARAM | ARG_PARAM_REQ | ARG_DEFAULT | ARG_ICASE,
        .opt   = 'S',
        .value = { .INT = &SUB_ADJ_EDGES }
      },
      {
        .name   = "ZonePath",
        .longopt= "zonepath",
        .desc   = "Path to the directory containing the zone files and zone collections",
        .type   = STRING,
        .flags  = ARG_PARAM | ARG_PARAM_REQ | ARG_DEFAULT | ARG_ICASE,
        .opt    = 'Z',
        .value  = { .STRING = &ZONE_PATH }
      } /*,
      {
        .name   = "SignalPath",
        .longopt= "signalpath",
        .desc   = "Path to the directory containing the files used as mode signals (unimplemented)",
        .type   = STRING,
        .flags  = ARG_PARAM | ARG_PARAM_REQ | ARG_ICASE,
        .opt    = 's',
        .value  = { .STRING = DEF_SIGNAL_PATH }
      } */
    };
    
    /* parse arguments */
    if (parse_params(argc, argv, (sizeof(args) / sizeof(argspec_t)), args) == -1) return -1;

    /* open logfile */
    open_logfile(log_filename);

    /* verify that the zone and signal paths exists */
    if (verify_directory(ZONE_PATH)) {
        _show_error("INIT", "Failed statting the zone path '%s': %s", ZONE_PATH, strerror(errno));
        return 1;
    }

#if 0
    /* unimplemented */
    if (verify_directory(SIGNAL_PATH)) {
        _show_error("INIT", "Failed statting the signal path '%s': %s", SIGNAL_PATH, strerror(errno));
        return 1;
    }
#endif

    /* initialize the engine */
    if (cellnet_init()) {
        _show_error("Init", "Failed to initialize cellnet engine!", 0);
        return 5;
    }


    printf("Params:\n  Lum Floor Multi: %u\n  Zone Min Multi: %u\n  Diff Floor: %u\n  Edge Floor: %0.3f\n  Sharp Floor: %0.3f\n  Min Adj Edges: %u\n  Sub Adj Edges: %u\n\n",
        LUM_FLOOR_MULTIPLIER, ZONE_MIN_MULTI, DIFF_FLOOR, CELL_EDGE_FLOOR, CELL_SHARP_FLOOR, MIN_ADJ_EDGES, SUB_ADJ_EDGES);

    /* main engine loop (this and init may differ between binaries) */
    return cellnet_main();

}

