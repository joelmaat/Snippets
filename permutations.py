"String permutation generator to solve the Code Eval challenge."

import sys


def permutations(string):
    "Yields all non-repeating string-length permutations of a given string."
    string = ''.join(sorted(string))
    todo = [(string[i], string[:i] + string[i + 1:])
            for i in reversed(xrange(len(string)))]
    append = todo.append
    pop = todo.pop
    while todo:
        permutation, remaining = pop()
        numLeft = len(remaining)
        if numLeft < 2:
            yield permutation + remaining
        else:
            for i in reversed(xrange(numLeft)):
                append((permutation + remaining[i], remaining[:i] + remaining[i + 1:]))


WRITE = sys.stdout.write
FLUSH = sys.stdout.flush
for word in open(sys.argv[1], 'r'):
    WRITE(','.join(permutations(word.rstrip())) + '\n')
    FLUSH()
