"Solver for 9x9 Sudoku puzzles."

import random
from itertools import izip, chain


def are_cells_poorly_formed(cells):
    "Returns True if cell isn't an int value between 0 and 9 (inclusive)."
    return any(type(cell) is not int or cell < 0 or cell > 9 for cell in cells)


def is_row_poorly_formed(row):
    "Returns True if row isn't a list of 9 element."
    return type(row) is not list or len(row) != 9


def is_value_repeated(row):
    "Returns true if there are duplicate entries in row.."
    row = [cell for cell in row if cell != 0]
    return len(set(row)) != len(row)


def get_square(grid, r_index, c_index):
    "Returns square cell at r_index, c_index belongs to."
    return (grid[r_index][c_index:c_index + 3]
            + grid[r_index + 1][c_index:c_index + 3]
            + grid[r_index + 2][c_index:c_index + 3])


def get_s_index(r_index, c_index):
    "Returns index of square containing cell at r_index, c_index."
    return (r_index / 3 * 3) + (c_index / 3)


def check_sudoku(grid):
    "Returns None if ill-formed, False if duplicates present, or True otherwise."
    if is_row_poorly_formed(grid) or any(is_row_poorly_formed(row) or are_cells_poorly_formed(row)
                                         for row in grid):
        return None
    return (not any(is_value_repeated(row) for row in chain(grid, izip(*grid))) and
            not any(is_value_repeated(get_square(grid, r, c))
                    for r in [0, 3, 6]
                    for c in [0, 3, 6]))


def get_index_with_least_options(zeros):
    "Returns index of element with the least number of valid options."
    z_index = best = None
    for index in xrange(len(zeros)):
        num_options = len(zeros[index][0])
        if best is None or num_options < best:
            if not num_options:
                return False
            best = num_options
            z_index = index
    return z_index


def remove_option(option, zeros, remove_option_from):
    "Removes given option from each specified index in zeros if present."
    option_set = set([option])
    z_copy = zeros[:]
    for index in remove_option_from:
        if option in z_copy[index][0]:
            z_copy[index] = (z_copy[index][0].copy() - option_set,) + z_copy[index][1:]
    return z_copy


def replace_zeros(grid, zeros):
    "Replaces blank cells with valid values, or returns False if no solution found."
    if not zeros:
        return grid
    z_index = get_index_with_least_options(zeros)
    if z_index is False:
        return False
    options, r_index, c_index, s_index = zeros.pop(z_index)
    remove_option_from = [index
                          for index, (_, r, c, s) in enumerate(zeros)
                          if r == r_index or c == c_index or s == s_index]
    for option in options:
        grid[r_index][c_index] = option
        solution = replace_zeros(grid, remove_option(option, zeros, remove_option_from))
        if solution:
            return solution
    return False


def create_metadata(rows, columns, squares, r_index, c_index):
    "Returns metadata for cell at specified r_index, c_index."
    s_index = get_s_index(r_index, c_index)
    options = rows[r_index] & columns[c_index] & squares[s_index]
    return (options, r_index, c_index, s_index)


def find_blank_cells(grid):
    "Returns indices of all empty cells and a set of valid values for each."
    universe = set(xrange(1, 10))
    rows = [universe - set(row) for row in grid]
    columns = [universe - set(row) for row in izip(*grid)]
    squares = [universe - set(get_square(grid, r, c))
               for r in [0, 3, 6]
               for c in [0, 3, 6]]
    indices = range(9)
    random.shuffle(indices)
    return [create_metadata(rows, columns, squares, r_index, c_index)
            for r_index in indices
            for c_index in indices
            if grid[r_index][c_index] == 0]


def solve_sudoku(grid):
    "Returns solved grid (expects 9x9 list with 0 for blank cells), or False if no solution found."
    return check_sudoku(grid) and replace_zeros([row[:] for row in grid], find_blank_cells(grid))


def generate_puzzle():
    "Returns a randomly generated puzzle (about 5-6% will have no solution)."
    dimension = 9
    max_num_cells_to_populate = 17
    puzzle = [[0] * dimension for _ in xrange(dimension)]
    available = range(dimension * dimension)
    rows, columns, squares = [[set(xrange(1, dimension + 1)) for _ in xrange(dimension)]
                              for _ in xrange(3)]
    for _ in xrange(max_num_cells_to_populate):
        index = random.randrange(len(available))
        available.pop(index)
        r_index = index / dimension
        c_index = index % dimension
        s_index = get_s_index(r_index, c_index)
        options = rows[r_index] & columns[c_index] & squares[s_index]
        if len(options) < 1:
            break
        value = random.choice(list(options))
        puzzle[r_index][c_index] = value
        any(cache.discard(value) for cache in [rows[r_index], columns[c_index], squares[s_index]])
    return puzzle

# print solve_sudoku(generate_puzzle())
