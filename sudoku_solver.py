import random


def are_cells_poorly_formed(cells):
    return any(type(cell) is not int or cell < 0 or cell > 9 for cell in cells)


def is_row_poorly_formed(row):
    return type(row) is not list or len(row) != 9


def is_value_repeated(row):
    row = [cell for cell in row if cell != 0]
    return len(set(row)) != len(row)


def get_square(grid, r_index, c_index):
    return (grid[r_index][c_index:c_index + 3]
                + grid[r_index + 1][c_index:c_index + 3]
                + grid[r_index + 2][c_index:c_index + 3])


def get_s_index(r_index, c_index):
    return (r_index / 3 * 3) + (c_index / 3)


def check_sudoku(grid):
    if is_row_poorly_formed(grid) or any(is_row_poorly_formed(row) or are_cells_poorly_formed(row)
                                         for row in grid):
        return None
    return (not any(is_value_repeated(row) for row in grid + zip(*grid)) and
            not any(is_value_repeated(get_square(grid, r, c))
                    for r in [0, 3, 6]
                    for c in [0, 3, 6]))


def get_index_with_least_options(zeros):
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
    option_set = set([option])
    z_copy = zeros[:]
    for index in remove_option_from:
        if option in z_copy[index][0]:
            z_copy[index] = (z_copy[index][0].copy() - option_set,) + z_copy[index][1:]
    return z_copy


def replace_zeros(grid, zeros):
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
    s_index = get_s_index(r_index, c_index)
    options = rows[r_index] & columns[c_index] & squares[s_index]
    return (options, r_index, c_index, s_index)


def find_blank_cells(grid):
    universe = set(xrange(1, 10))
    rows = [universe - set(row) for row in grid]
    columns = [universe - set(row) for row in zip(*grid)]
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
    "Grid should be a list of 9 lists, each with 9 ints (range: 0 - 9). 0 for blank cells."
    return check_sudoku(grid) and replace_zeros([row[:] for row in grid], find_blank_cells(grid))


def generate_puzzle():
    "About 5-6% will have no solution."
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
