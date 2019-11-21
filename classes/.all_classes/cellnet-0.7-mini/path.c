#define __PATH_C__
#include "path.h"
#include <stdlib.h>
#include <unistd.h>
#include <sys/time.h>
#include <time.h>
#include "debug.h"
#define JUNCTION_CACHE 1000
#define PATH_CACHE 1000
#define PATHFL_INV 1
#define PATHFL_RAND 2

extern FILE *logfile;

static uint32_t cached_juncs = 0;
static junc_t junc_cache[JUNCTION_CACHE];
static uint32_t cached_paths = 0;
static path_t path_cache[PATH_CACHE];

static inline junc_t *allocate_junc() {
    if (cached_juncs == JUNCTION_CACHE) {
        _show_error("Path", "Junction cache full! (%u)", JUNCTION_CACHE);
        return NULL;
    }
    return &(junc_cache[cached_juncs++]);
}

static inline path_t *allocate_path() {
    if (cached_paths == PATH_CACHE) {
        _show_error("Path", "Path cache full! (%u)", PATH_CACHE);
        return NULL;
    }
    return &(path_cache[cached_paths++]);
}

path_t *make_path(int8_t x, int8_t y) {
    path_t *path = allocate_path();
    if (!path) return NULL;
    path->x = x; path->y = y;
    return path;
}

path_t *path_to(path_t *orig, junc_t *p, junc_t *f) {
    path_t *path = allocate_path();
    if (!path) return NULL;
    path->x = orig->x;
    path->y = orig->y;
    path->pass = p;
    path->fail = f;
    return path;
}

junc_t *make_junc (
  path_t *l0, path_t *l1, path_t *l2, path_t *l3,
  path_t *l4, path_t *l5, path_t *l6, path_t *l7
) {
    junc_t *junc = allocate_junc();
    path_t **lk = junc->link;
    lk[0] = l0; lk[1] = l1;
    lk[2] = l2; lk[3] = l3;
    lk[4] = l4; lk[5] = l5;
    lk[6] = l6; lk[7] = l7;
    return junc;
}

junc_t *set_null_paths(junc_t *j, junc_t *p, junc_t *f) {
    if (p) {
        if (!(j->link[0]->pass)) j->link[0]->pass = p;
        if (!(j->link[1]->pass)) j->link[1]->pass = p;
        if (!(j->link[2]->pass)) j->link[2]->pass = p;
        if (!(j->link[3]->pass)) j->link[3]->pass = p;
        if (!(j->link[4]->pass)) j->link[4]->pass = p;
        if (!(j->link[5]->pass)) j->link[5]->pass = p;
        if (!(j->link[6]->pass)) j->link[6]->pass = p;
        if (!(j->link[7]->pass)) j->link[7]->pass = p;
    }

    if (f) {
        if (!(j->link[0]->fail)) j->link[0]->fail = f;
        if (!(j->link[1]->fail)) j->link[1]->fail = f;
        if (!(j->link[2]->fail)) j->link[2]->fail = f;
        if (!(j->link[3]->fail)) j->link[3]->fail = f;
        if (!(j->link[4]->fail)) j->link[4]->fail = f;
        if (!(j->link[5]->fail)) j->link[5]->fail = f;
        if (!(j->link[6]->fail)) j->link[6]->fail = f;
        if (!(j->link[7]->fail)) j->link[7]->fail = f;
    }
    return j;
}
junc_t *range_paths(junc_t *j, junc_t *p, junc_t *f, int start, int end, int step) {
    for (; start <= end; start += step) {
        j->link[(start & 0x7)]->pass = p;
        j->link[(start & 0x7)]->fail = f;
    }
    return j;
}
junc_t *set_all_paths(junc_t *j, junc_t *p, junc_t *f) {
    j->link[0]->pass = j->link[1]->pass = 
    j->link[2]->pass = j->link[3]->pass = 
    j->link[4]->pass = j->link[5]->pass = 
    j->link[6]->pass = j->link[7]->pass = p;

    j->link[0]->fail = j->link[1]->fail = 
    j->link[2]->fail = j->link[3]->fail = 
    j->link[4]->fail = j->link[5]->fail = 
    j->link[6]->fail = j->link[7]->fail = f;

    return j;
}

junc_t *set_paths(junc_t *j,
  junc_t *p0, junc_t *f0, junc_t *p1, junc_t *f1, junc_t *p2, junc_t *f2, junc_t *p3, junc_t *f3, 
  junc_t *p4, junc_t *f4, junc_t *p5, junc_t *f5, junc_t *p6, junc_t *f6, junc_t *p7, junc_t *f7 
) {
    j->link[0]->pass = p0; j->link[1]->pass = p1;
    j->link[2]->pass = p2; j->link[3]->pass = p3;
    j->link[4]->pass = p4; j->link[5]->pass = p5;
    j->link[6]->pass = p6; j->link[7]->pass = p7;

    j->link[0]->fail = f0; j->link[1]->fail = f1;
    j->link[2]->fail = f2; j->link[3]->fail = f3;
    j->link[4]->fail = f4; j->link[5]->fail = f5;
    j->link[6]->fail = f6; j->link[7]->fail = f7;

    return j;
}

junc_t *use_junc (junc_t *orig, int8_t skew, uint8_t step, uint32_t flags) {
    junc_t *junc = allocate_junc();
    if (!junc) return NULL;
    int c, i;
    int base = (flags & PATHFL_INV)?-7:0;

    if (flags & PATHFL_RAND) {
        struct timeval stbuf;
        gettimeofday(&stbuf, NULL);
        if (!skew) skew = (0xFF & (stbuf.tv_usec>>8));
        if (!step) step = (0xFF & (stbuf.tv_usec>>16));
    }

    for (c = 0, i = skew; c < 8; c++) {
        junc->link[c] = orig->link[abs(base + (i & 0x7))];
        i += step;
        if (i == skew) i++;
    }

    return junc;
}

junc_t *knights_moves;
junc_t *kings_moves;
junc_t *knights_to_kings;

int init_paths () {
  knights_moves = make_junc(
    make_path(-1, -2),  // NNW
    make_path( 1, -2),  // NNE 
    make_path(-2, -1),  // NWW
    make_path( 2, -1),  // NEE
    make_path(-1,  2),  // SSW
    make_path( 1,  2),  // SSE
    make_path(-2,  1),  // SWW
    make_path( 1,  1)   // SEE
  );

  /* sets all paths in knights_moves to a copy
   * of knights moves where the links are skewed
   * by 1, stepped by 2.  The copy of knights_moves
   * is also getting all its paths to point back
   * to the original linking to create a two-layer
   * junction map
   */
  {
    junc_t *knights_o1s2i, *knights_o4s2i;

    set_all_paths(
      knights_moves,                        // set paths in knights_moves
      knights_o1s2i = set_all_paths(     // to a copy of knights_moves
        use_junc(         
          knights_moves,                    // copy knights moves
          1, 2,                             // skew by 1, step by 2
          PATHFL_INV                        // invert results
        ),
        knights_moves,
        knights_o4s2i = use_junc(
          knights_moves,
          4, 2,
          PATHFL_INV
        )
      ),
      NULL
    );
    set_null_paths(knights_moves, NULL, knights_o4s2i);
  }

  {
    kings_moves = make_junc(
      make_path( 0, -1),  // N
      make_path( 1, -1),  // NE
      make_path( 1,  0),  // E
      make_path( 1,  1),  // SE
      make_path( 0,  1),  // S
      make_path(-1,  1),  // SW
      make_path(-1,  0),  // W
      make_path(-1, -1)   // NW
    );
  
    junc_t *kings_o3s2i, *kings_o6s2i;
    range_paths(
      kings_moves,
      kings_o3s2i = set_all_paths(
        use_junc(kings_moves, 3, 2, PATHFL_INV),
        kings_moves,
        kings_o6s2i = use_junc(kings_moves, 6, 2, PATHFL_INV)
      ),
      kings_moves,
      6, 9, 1
    );

    set_null_paths(kings_o6s2i, kings_o3s2i, kings_moves);

    range_paths(
      kings_moves,
      kings_o6s2i,
      kings_moves,
      2, 5, 1
    );

  }


  {
    junc_t *knight2king_o3s2, *knight2king_o6s2;

    knights_to_kings = set_all_paths(
      use_junc(knights_moves, 0, 1, 0),
      knight2king_o3s2 = use_junc(kings_moves, 3, 2, 0),
      knight2king_o6s2 = use_junc(kings_moves, 6, 2, 0)
    );

    set_all_paths(
      knight2king_o3s2, 
      set_all_paths(
        use_junc(knights_moves, 1, 2, 0),
        knights_to_kings,
        knight2king_o3s2
      ),
      set_all_paths(
        use_junc(knights_moves, 3, 2, 0),
        knights_to_kings,
        knight2king_o6s2
      )
    );

    set_all_paths(
      knight2king_o6s2,
      knight2king_o3s2->link[0]->fail,
      knight2king_o3s2->link[0]->pass
    );
  }

  return 0;
}


static const char *depthstr = "                                         ";
#define DSTR printf("%2d: %s", depth, depthstr + (40 - (depth*4)))

int junc_trace (junc_t *junc, int x, int y, int max_depth, uint8_t btree[], int depth) {
    int i;
    DSTR;
    printf("=> (%d x %d):\n", x, y);
    uint8_t tree = btree[depth];
    
    if (depth == (max_depth-1)) {
        DSTR;
//        printf("Depth reached: %d\n", depth);
        return 0;
    }
    int ret = 0;
    for (i = 0; i < 8; i++) {
        int r = 0;
        DSTR;
        if (tree & (1<<depth)) {
            printf("** [%d]: pass =>\n", i);
            r = 1 + junc_trace(junc->link[i]->pass, x + junc->link[i]->x, y + junc->link[i]->y, max_depth, btree, depth + 1);
        } else {
            printf("** [%d]: fail =>\n", i);
            r = junc_trace(junc->link[i]->fail, x + junc->link[i]->x, y + junc->link[i]->y, max_depth, btree, depth + 1);
        }
        DSTR;
        printf("-- [%d]: %d traced\n", i, r);
        ret += r;
    }

    DSTR;
    printf("<= (%d x %d): %d traced\n", x, y, ret);

    return ret;
}


