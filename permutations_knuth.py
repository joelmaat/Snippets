"String permutation generator to solve the CodeEval challenge."

import sys


def permutations(string):
    "Yields all non-repeating string-length permutations of a given string."
    string = ''.join(sorted(string))
    length = len(string)
    while True:
        yield string

        left = next((index - 1 for index in xrange(length - 1, 0, -1)
                     if string[index] > string[index - 1]),
                    None)

        if left is None:
            return

        right = next((index
                      for index in xrange(length - 1, left, -1)
                      if string[left] < string[index]),
                     left + 1)

        # Swap left and right characters, then reverse everything after left's original location.
        string = (string[:left]
                  + string[right:right + 1]
                  + (string[left + 1:right] + string[left:left + 1] + string[right + 1:])[::-1])


sys.stdout.writelines(','.join(permutations(word.rstrip())) + '\n'
                      for word in open(sys.argv[1], 'r'))
