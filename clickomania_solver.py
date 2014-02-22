#!/bin/python

"Solves a Click-o-mania (Collapse) puzzle if a solution exists"

from collections import namedtuple
from heapq import heappush, heappop
import pickle

COLORS = '-ROYGBIV'
COLOR_TO_INDEX_MAPPING = {v: i for i, v in enumerate(COLORS)}
BITS_PER_COLOR = (len(COLORS) - 1).bit_length()
BLANK_GRID_BITS = BLANK_COLUMN_BITS = 0
BLANK_CELL = COLOR_TO_INDEX_MAPPING['-']
MOVES_CACHE = {}


class Grid(namedtuple('GridBase', 'num_rows num_columns num_colors bits')):
    "Stores data about a Click-o-mania grid."
    def replace(self, **kwds):
        "Wrapper for _replace method."
        return super(Grid, self)._replace(**kwds)


def xy_to_bit_position(x_coord, y_coord, grid):
    """
        ORRR
        GYGG
        BBVB

        Is stored as:

        BGR VGR BYR BGO

        AKA:

        column[y_coord] (row[x_coord] - row[0]) - column[0] (row[x_coord] - row[0])

        So the coord 0,0 is stored at bit 0, then 1,0 is stored at bit 1 (times bits per color),
        etc..
    """
    return (y_coord * grid.num_rows + x_coord) * BITS_PER_COLOR


def get_one_cell_mask():
    "Returns mask to get data for one cell"
    return (1 << BITS_PER_COLOR) - 1  # 3 bits = 0b111


def get_one_column_mask(grid):
    "Returns mask to get data for one column"
    return (1 << get_bits_per_column(grid)) - 1


def get_bits_per_column(grid):
    "Returns number of bits needed to represent one column"
    return grid.num_rows * BITS_PER_COLOR


def swap_bit_range(bits, replace_with, range_length, position):
    "Clears all bits in range, then inserts replace_with"
    range_mask = ((1 << range_length) - 1) << position
    return (bits ^ (bits & range_mask)) | (replace_with << position)


def delete_bit_range(bits, range_length, position):
    "Deletes (not just clears) all bits in the specified range"
    lower_bits = (1 << position) - 1
    return (bits >> (position + range_length) << position) | (bits & lower_bits)


def clear_cells(group, grid):
    "Sets cells to blank"
    bits = grid.bits
    for x_coord, y_coord in group:
        bits = swap_bit_range(bits,
                              BLANK_CELL,
                              BITS_PER_COLOR,
                              xy_to_bit_position(x_coord, y_coord, grid))
    return grid.replace(bits=bits)


def collapse_grid(group, grid):
    "Find blank columns and remove. Compact columns"
    y_coords = set(y_coord for x_coord, y_coord in group)
    x_coords = set(x_coord for x_coord, y_coord in group)
    column_mask = get_one_column_mask(grid)
    bits_per_column = get_bits_per_column(grid)
    one_cell_mask = get_one_cell_mask()
    bits = grid.bits
    for y_coord in xrange(grid.num_columns - 1, -1, -1):
        if y_coord not in y_coords:
            continue
        column_position = xy_to_bit_position(0, y_coord, grid)
        column = (bits >> column_position) & column_mask
        if column == BLANK_COLUMN_BITS:
            bits = delete_bit_range(bits, bits_per_column, column_position)
        else:
            for x_coord in xrange(grid.num_rows):
                if x_coord not in x_coords:
                    continue
                color = (column >> (x_coord * BITS_PER_COLOR)) & one_cell_mask
                if color == BLANK_CELL:
                    column = delete_bit_range(column,
                                              BITS_PER_COLOR,
                                              x_coord * BITS_PER_COLOR) << BITS_PER_COLOR
                    column |= BLANK_CELL
            bits = swap_bit_range(bits, column, bits_per_column, column_position)

    return grid.replace(bits=bits)


def get_groups(grid):
    "Get all groups of (non-diagonally) connected cells"
    groups = []
    grouped = {}
    one_cell_mask = get_one_cell_mask()
    bits = grid.bits
    color_appears = [0] * len(COLORS)
    for y_coord in xrange(grid.num_columns):
        for x_coord in xrange(grid.num_rows):
            color = bits & one_cell_mask
            bits >>= BITS_PER_COLOR
            if color != BLANK_CELL:
                color_appears[color] += 1
                group = None
                for position in [(x_coord, y_coord - 1, color),
                                 (x_coord - 1, y_coord, color),
                                 (x_coord + 1, y_coord, color),
                                 (x_coord, y_coord + 1, color)]:
                    if position in grouped and grouped[position] != group:
                        if group is None:
                            group = grouped[position]
                        else:
                            other = grouped[position]
                            groups[group] += groups[other]
                            grouped.update(((ox_coord, oy_coord, color), group)
                                           for ox_coord, oy_coord in groups[other])
                            groups[other] = None
                if group is None:
                    group = len(groups)
                    groups += [[]]
                groups[group] += [(x_coord, y_coord)]
                grouped[(x_coord, y_coord, color)] = group
    return [group for group in groups if group]


def get_incremental_moves(grid):
    "Get all possible collapses (gives one coordinate per collapsed group)"
    return [(len(group), group[0][0], group[0][1], collapse_grid(group, clear_cells(group, grid)))
            for group in get_groups(grid)
            if len(group) > 1]


def get_next_move(grid):
    "Returns x, y coordinate for next cell to collapse"
    if grid in MOVES_CACHE:
        return MOVES_CACHE[grid]
    todo = []
    for collapsed, x_coord, y_coord, ngrid in get_incremental_moves(grid):
        heappush(todo, (-collapsed, ngrid, [(x_coord, y_coord, ngrid)]))
    tried = set([grid]) | set(ngrid for _, _, moves in todo for _, _, ngrid in moves)
    x_coord, y_coord = 0, 0
    best = (x_coord, y_coord, 0)
    while todo:
        collapsed, _, moves = heappop(todo)
        x_coord, y_coord, cgrid = moves[-1]
        if -collapsed > best[-1]:
            best = (moves[0][0], moves[0][1], -collapsed)
        if cgrid.bits == BLANK_GRID_BITS:
            MOVES_CACHE[grid] = moves[0][:2]
            for i in range(1, len(moves)):
                MOVES_CACHE[moves[i - 1][-1]] = moves[i][:2]
            return MOVES_CACHE[grid]
        for ncollapsed, nx_coord, ny_coord, ngrid in get_incremental_moves(cgrid):
            if ngrid not in tried:
                tried.add(ngrid)
                ncollapsed = collapsed - ncollapsed
                heappush(todo, (ncollapsed, ngrid, moves + [(nx_coord, ny_coord, ngrid)]))

    return best[:-1]


def read_moves_cache_from_file(filename):
    "Reads MOVES_CACHE from file if file exists."
    global MOVES_CACHE
    try:
        infile = open(filename, 'rb')
    except IOError:
        return
    MOVES_CACHE = pickle.load(infile)
    infile.close()


def save_moves_cache_to_file(filename):
    "Saves MOVES_CACHE to file to avoid recomputation across calls."
    outfile = open(filename, 'wb')
    pickle.dump(MOVES_CACHE, outfile, -1)
    outfile.close()


def run():
    "Reads in grid then returns x, y coordinate for next cell to collapse "
    num_rows, num_columns, num_colors = [int(value) for value in raw_input().strip().split()]
    bits = 0
    grid = Grid(num_rows, num_columns, num_colors, bits)
    for x_coord in xrange(num_rows):
        row = raw_input().strip()
        for y_coord in xrange(num_columns):
            position = xy_to_bit_position(x_coord, y_coord, grid)
            bits |= COLOR_TO_INDEX_MAPPING[row[y_coord]] << position

    grid = grid.replace(bits=bits)

    moves_cache_file = 'JKJ_MOVES_CACHE'
    read_moves_cache_from_file(moves_cache_file)

    x_coord, y_coord = get_next_move(grid)
    print x_coord, y_coord

    save_moves_cache_to_file(moves_cache_file)


"""
# Tests

# Change run def to: def run(raw_input=raw_input):, then run

def show_grid(grid):
    "Graphical representation of grid"
    for x_coord in xrange(grid.num_rows):
        for y_coord in xrange(grid.num_columns):
            position = xy_to_bit_position(x_coord, y_coord, grid)
            print COLORS[(grid.bits >> position) & get_one_cell_mask()],
        print


def fake_input():
    "Fake grids for testing"
    board = '''
            12 20 3
            GGGYBIBBGYBGYBIYIIYB
            GYGIGIGBBGGYIGYGBGBG
            IYBBBGGYGBGBGYBYGYII
            BYIYYYYBBGBIGIIGGYBB
            BBYIIGGGGBGGIIGYGBYG
            GGBYBYGBBGIBBYYBGGBY
            GBGIYYYIIBGYGGBIYYBY
            IBYIIYYYBIYGGYYYGIIG
            YGBBYIGIGIBYYYGGGGGY
            GYBYIIBYIGYGGIBBBBBB
            BYGYIIGYGYBBGYGIYIBG
            GIYBGIGIGYIGIYYYIYYY
            '''

    data = iter(board.strip().split('\n'))

    return lambda: next(data)

run(fake_input())
"""
