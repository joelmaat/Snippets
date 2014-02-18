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
Grid = namedtuple('Grid', 'numRows numColumns numColors bits')
MOVES_CACHE = {}


def xyToBitPosition(x, y, grid):
    """
        ORRR
        GYGG
        BBVB

        Is stored as:

        BGR VGR BYR BGO

        AKA:

        column[y] (row[x] - row[0]) - column[0] (row[x] - row[0])

        So the coord 0,0 is stored at bit 0, then 1,0 is stored at bit 1 (times bits per color), etc..
    """
    return (y * grid.numRows + x) * BITS_PER_COLOR


def getOneCellMask():
    "Returns mask to get data for one cell"
    return (1 << BITS_PER_COLOR) - 1  # 3 bits = 0b111


def getOneColumnMask(grid):
    "Returns mask to get data for one column"
    return (1 << getBitsPerColumn(grid)) - 1


def getBitsPerColumn(grid):
    "Returns number of bits needed to represent one column"
    return grid.numRows * BITS_PER_COLOR


def swapBitRange(bits, replaceWith, rangeLength, position):
    "Clears all bits in range, then inserts replaceWith"
    rangeMask = ((1 << rangeLength) - 1) << position
    return (bits ^ (bits & rangeMask)) | (replaceWith << position)


def deleteBitRange(bits, rangeLength, position):
    "Deletes (not just clears) all bits in the specified range"
    lowerBits = (1 << position) - 1
    return (bits >> (position + rangeLength) << position) | (bits & lowerBits)


def clearCells(group, grid):
    "Sets cells to blank"
    bits = grid.bits
    for x, y in group:
        bits = swapBitRange(bits, BLANK_CELL, BITS_PER_COLOR, xyToBitPosition(x, y, grid))
    return grid._replace(bits=bits)


def collapseGrid(group, grid):
    "Find blank columns and remove. Compact columns"
    ys = set(y for x, y in group)
    xs = set(x for x, y in group)
    columnMask = getOneColumnMask(grid)
    bitsPerColumn = getBitsPerColumn(grid)
    oneCellMask = getOneCellMask()
    bits = grid.bits
    for y in xrange(grid.numColumns - 1, -1, -1):
        if y not in ys:
            continue
        columnPosition = xyToBitPosition(0, y, grid)
        column = (bits >> columnPosition) & columnMask
        if column == BLANK_COLUMN_BITS:
            bits = deleteBitRange(bits, bitsPerColumn, columnPosition)
        else:
            for x in xrange(grid.numRows):
                if x not in xs:
                    continue
                color = (column >> (x * BITS_PER_COLOR)) & oneCellMask
                if color == BLANK_CELL:
                    column = deleteBitRange(column, BITS_PER_COLOR, x * BITS_PER_COLOR) << BITS_PER_COLOR
                    column |= BLANK_CELL
            bits = swapBitRange(bits, column, bitsPerColumn, columnPosition)

    return grid._replace(bits=bits)


def getGroups(grid):
    "Get all groups of (non-diagonally) connected cells"
    groups = []
    grouped = {}
    oneCellMask = getOneCellMask()
    bits = grid.bits
    colorAppears = [0] * len(COLORS)
    for y in xrange(grid.numColumns):
        for x in xrange(grid.numRows):
            color = bits & oneCellMask
            bits >>= BITS_PER_COLOR
            if color != BLANK_CELL:
                colorAppears[color] += 1
                group = None
                for position in [(x, y - 1, color),
                                 (x - 1, y, color),
                                 (x + 1, y, color),
                                 (x, y + 1, color)]:
                    if position in grouped and grouped[position] != group:
                        if group is None:
                            group = grouped[position]
                        else:
                            other = grouped[position]
                            groups[group] += groups[other]
                            grouped.update(((ox, oy, color), group) for ox, oy in groups[other])
                            groups[other] = None
                if group is None:
                    group = len(groups)
                    groups += [[]]
                groups[group] += [(x, y)]
                grouped[(x, y, color)] = group
    return [group for group in groups if group]


def getIncrementalMoves(grid):
    "Get all possible collapses (gives one coordinate per collapsed group)"
    return [(len(group), group[0][0], group[0][1], collapseGrid(group, clearCells(group, grid)))
            for group in getGroups(grid)
            if len(group) > 1]


def getNextMove(grid):
    "Returns x, y coordinate for next cell to collapse"
    if grid in MOVES_CACHE:
        return MOVES_CACHE[grid]
    todo = []
    for collapsed, x, y, ngrid in getIncrementalMoves(grid):
        heappush(todo, (-collapsed, ngrid, [(x, y, ngrid)]))
    tried = set([grid]) | set(ngrid for _, _, moves in todo for _, _, ngrid in moves)
    x, y = 0, 0
    best = (x, y, 0)
    while todo:
        collapsed, _, moves = heappop(todo)
        x, y, cgrid = moves[-1]
        if -collapsed > best[-1]:
            best = (moves[0][0], moves[0][1], -collapsed)
        if cgrid.bits == BLANK_GRID_BITS:
            MOVES_CACHE[grid] = moves[0][:2]
            for i in range(1, len(moves)):
                MOVES_CACHE[moves[i - 1][-1]] = moves[i][:2]
            return MOVES_CACHE[grid]
        for ncollapsed, nx, ny, ngrid in getIncrementalMoves(cgrid):
            if ngrid not in tried:
                tried.add(ngrid)
                heappush(todo, (collapsed - ncollapsed, ngrid, moves + [(nx, ny, ngrid)]))

    return best[:-1]


def readMovesCacheFromFile(filename):
    "Reads MOVES_CACHE from file if file exists."
    global MOVES_CACHE
    try:
        infile = open(filename, 'rb')
    except IOError:
        return
    MOVES_CACHE = pickle.load(infile)
    infile.close()


def saveMovesCacheToFile(filename):
    "Saves MOVES_CACHE to file to avoid recomputation across calls."
    outfile = open(filename, 'wb')
    pickle.dump(MOVES_CACHE, outfile, -1)
    outfile.close()


def run(raw_input=raw_input):
    "Reads in grid then returns x, y coordinate for next cell to collapse "
    numRows, numColumns, numColors = [int(value) for value in raw_input().strip().split()]
    bits = 0
    grid = Grid(numRows, numColumns, numColors, bits)
    for x in xrange(numRows):
        row = raw_input().strip()
        for y in xrange(numColumns):
            bits |= COLOR_TO_INDEX_MAPPING[row[y]] << xyToBitPosition(x, y, grid)

    grid = grid._replace(bits=bits)

    moves_cache_file = 'JKJ_MOVES_CACHE'
    readMovesCacheFromFile(moves_cache_file)

    removeX, removeY = getNextMove(grid)
    print removeX, removeY

    saveMovesCacheToFile(moves_cache_file)


def showGrid(grid):
    "Graphical representation of grid"
    for x in xrange(grid.numRows):
        for y in xrange(grid.numColumns):
            print COLORS[(grid.bits >> xyToBitPosition(x, y, grid)) & getOneCellMask()],
        print


def fakeInput():
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

run(fakeInput())
