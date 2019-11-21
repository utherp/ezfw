#include <stdlib.h>
#include <stdio.h>
#include <unistd.h>

#define _DEBUG_MAIN_
#include "debug.h"

#include "path.h"
unsigned int frame_number = 0;
unsigned char *buffer;
unsigned int _PIXELS_;
unsigned int entr;
unsigned int skipped;

int main (int c, char **v) {
    logfile = stdout;
    int depth = (c>1)?atoi(v[1]):3;
    int x = 100;
    int y = 100;
    uint8_t btree[10] = {
        0x3F, 0xB2, 0xF2, 0x83, 0x52, 0x66, 0x73, 0x99, 0x24, 0x69
    };

    fprintf(stderr, "initializing paths...\n");
    init_paths();

    fprintf(stderr, "done init, testing knights moves...\n");

    int ret = junc_trace(knights_moves, x, y, depth, btree, 0);

    fprintf(stderr, "\ndone.  traced %d cells\n", ret);

    return 0;
}

