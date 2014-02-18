import random


def areCellsPoorlyFormed(cells):
    return any(type(cell) is not int or cell < 0 or cell > 9 for cell in cells)


def isRowPoorlyFormed(row):
    return type(row) is not list or len(row) != 9


def isValueRepeated(row):
    row = [cell for cell in row if cell != 0]
    return len(set(row)) != len(row)


def check_sudoku(grid):
    if isRowPoorlyFormed(grid) or any(isRowPoorlyFormed(row) or areCellsPoorlyFormed(row)
                                      for row in grid):
        return None
    return (not any(isValueRepeated(row) for row in grid + zip(*grid)) and
            not any(isValueRepeated(grid[r][c:c + 3] + grid[r + 1][c:c + 3] + grid[r + 2][c:c + 3])
                    for r in [0, 3, 6]
                    for c in [0, 3, 6]))


def getIndexWithLeastOptions(zeros):
    zIndex = best = None
    for index in xrange(len(zeros)):
        numOptions = len(zeros[index][0])
        if best is None or numOptions < best:
            if not numOptions:
                return False
            best = numOptions
            zIndex = index
    return zIndex


def removeOption(option, zeros, removeOptionFrom):
    optionSet = set([option])
    zCopy = zeros[:]
    for index in removeOptionFrom:
        if option in zCopy[index][0]:
            zCopy[index] = (zCopy[index][0].copy() - optionSet,) + zCopy[index][1:]
    return zCopy


def replaceZeros(grid, zeros):
    if not zeros:
        return grid
    zIndex = getIndexWithLeastOptions(zeros)
    if zIndex is False:
        return False
    options, rIndex, cIndex, sIndex = zeros.pop(zIndex)
    removeOptionFrom = [index
                        for index, (_, r, c, s) in enumerate(zeros)
                        if r == rIndex or c == cIndex or s == sIndex]
    for option in options:
        grid[rIndex][cIndex] = option
        solution = replaceZeros(grid, removeOption(option, zeros, removeOptionFrom))
        if solution:
            return solution
    return False


def createMetadata(rows, columns, squares, rIndex, cIndex):
    sIndex = (rIndex / 3 * 3) + (cIndex / 3)
    options = rows[rIndex] & columns[cIndex] & squares[sIndex]
    return (options, rIndex, cIndex, sIndex)


def findBlankCells(grid):
    universe = set(xrange(1, 10))
    rows = [universe - set(row) for row in grid]
    columns = [universe - set(row) for row in zip(*grid)]
    squares = [universe - set(grid[r][c:c + 3] + grid[r + 1][c:c + 3] + grid[r + 2][c:c + 3])
               for r in [0, 3, 6]
               for c in [0, 3, 6]]
    indices = range(9)
    random.shuffle(indices)
    return [createMetadata(rows, columns, squares, rIndex, cIndex)
            for rIndex in indices
            for cIndex in indices
            if grid[rIndex][cIndex] == 0]


def solve_sudoku(grid):
    "Grid should be a list of 9 lists, each with 9 ints (range: 0 - 9). 0 for blank cells."
    return check_sudoku(grid) and replaceZeros([row[:] for row in grid], findBlankCells(grid))


def generatePuzzle():
    "About 5-6% will have no solution."
    dimension = 9
    maxNumCellsToPopulate = 17
    puzzle = [[0] * dimension for _ in xrange(dimension)]
    available = range(dimension * dimension)
    rows, columns, squares = [[set(xrange(1, dimension + 1)) for _ in xrange(dimension)] for _ in xrange(3)]
    for _ in xrange(maxNumCellsToPopulate):
        index = random.randrange(len(available))
        available.pop(index)
        rIndex = index / dimension
        cIndex = index % dimension
        sIndex = (rIndex / 3 * 3) + (cIndex / 3)
        options = rows[rIndex] & columns[cIndex] & squares[sIndex]
        if len(options) < 1:
            break
        value = random.choice(list(options))
        puzzle[rIndex][cIndex] = value
        any(cache.discard(value) for cache in [rows[rIndex], columns[cIndex], squares[sIndex]])
    return puzzle

# for _ in xrange(100): print solve_sudoku(generatePuzzle())
