"Simple in-memory database as a response to the Thumbtack coding challenge."

from collections import OrderedDict


class SimpleDb(object):

    "Simple in-memory database as a response to the Thumbtack coding challenge."

    def __init__(self):
        "Initialize SimpleDb instance."
        self._db = {}
        self._value_frequency = {}
        self._num_open_transactions = 0
        self._transactions = {}
        self._transaction_value_frequency = {}

    def put(self, name, value):
        "Inserts/updates value of name in database."
        self._update_value(name, value)

    def get(self, name):
        "Returns value of name if it exists in the database, otherwise returns None."
        if self._is_transaction_open() and name in self._transactions:
            return self._transactions[name][self.length(self._transactions[name])]
        elif name in self._db:
            return self._db[name]
        else:
            return None

    def get_num_equal_to(self, value):
        "Returns number of entries in the database that have the specified value."
        t_val_freq = self._transaction_value_frequency
        return ((self._value_frequency[value] if value in self._value_frequency else 0) +
                (t_val_freq[value] if self._is_transaction_open() and value in t_val_freq else 0))

    def unset(self, name):
        "Removes name from database if it's present."
        self._update_value(name, None)

    def begin(self):
        "Opens transaction block."
        self._num_open_transactions += 1

    def rollback(self):
        "Rolls back most recent transaction, returning False if no open transactions."
        if not self._is_transaction_open():
            return False
        for name in self._transactions.keys():
            if self._num_open_transactions in self._transactions[name]:
                value = self._transactions[name].pop(self._num_open_transactions)
                if not self._transactions[name]:
                    del self._transactions[name]
                self._update_num_equal_to(value, self.get(name))
        self._num_open_transactions -= 1
        return True

    def commit(self):
        "Commits all transactions, returning False if no open transactions."
        if not self._is_transaction_open():
            return False
        self._num_open_transactions = 0
        any(self._update_value(name, values[self._get_last_key(values)])
            for name, values in self._transactions.iteritems())
        self._transactions = {}
        self._transaction_value_frequency = {}
        return True

    def _get_last_key(self, ordered):
        "Returns key (or value if list, etc) most recently added to ordered."
        return next(reversed(ordered)) if ordered else None

    def _is_transaction_open(self):
        "Returns True if there is currently a pending transaction. Returns False otherwise."
        return self._num_open_transactions > 0

    def _update_num_equal_to(self, current_value, new_value=None):
        "Swaps current_value (lowers count by 1) with new_value (add 1). Skips None values."
        target = (self._transaction_value_frequency if self._is_transaction_open() else
                  self._value_frequency)
        for amount_to_add, value in [(-1, current_value), (1, new_value)]:
            if value is not None:
                target.setdefault(value, 0)
                target[value] += amount_to_add

    def _update_value(self, name, value):
        "Updates value (adding/editing/deleting) in database."
        current_value = self.get(name)
        if current_value == value:
            return
        elif self._is_transaction_open():
            self._transactions.setdefault(name, OrderedDict())
            self._transactions[name][self._num_open_transactions] = value
        elif value is None:
            del self._db[name]
        else:
            self._db[name] = value
        self._update_num_equal_to(current_value, value)


def display(value, default=None):
    "Prints value to stdout, or default if value is None and default is not None."
    print value if value is not None or default is None else default


OPS = {
    'SET':        (3, lambda db, name, value: db.put(name, value)),
    'GET':        (2, lambda db, name:        display(db.get(name), "NULL")),
    'NUMEQUALTO': (2, lambda db, value:       display(db.get_num_equal_to(value))),
    'UNSET':      (2, lambda db, name:        db.unset(name)),
    'BEGIN':      (1, lambda db:              db.begin()),
    'ROLLBACK':   (1, lambda db:              db.rollback() or display("NO TRANSACTION")),
    'COMMIT':     (1, lambda db:              db.commit() or display("NO TRANSACTION")),
    'END':        (1, lambda db:              False)
}


def process_command(simple_db, command):
    "Applies command to the database. Returns False when stream of commands should end."
    command = command.split()
    opcode = command.pop(0).upper() if command else None
    if opcode is None or opcode not in OPS or len(command) != (OPS[opcode][0] - 1):
        print "INVALID COMMAND"
    elif 'END' == opcode:
        return False
    else:
        OPS[opcode][1](simple_db, *command)
    return True


def run():
    "Reads commands from the command line and passes them through for processing."
    simple_db = SimpleDb()
    all(iter(lambda: process_command(simple_db, raw_input()), False))

run()

"""
# Tests

# Change run def to ---- def run(raw_input=raw_input): ---- then run

def fake_input(commands=None):
    "Allows faking of stdin data."
    data = ((command for command in commands.split('\n')) if commands else
            (line for block in raw_input().split('\n') for line in block.split('\n')))
    return lambda: next(data)


# 10 NULL 2 0 1 10 20 10 NULL 40 NO TRANSACTION 50 NULL 60 60 1 0 1
run(fake_input("SET ex 10 \n GET ex \n ""UNSET ex \n GET ex \n END"))
run(fake_input("SET a 10 \n SET b 10 \n NUMEQUALTO 10 \n NUMEQUALTO 20 \n SET b 30 \n "
               "NUMEQUALTO 10 \n END"))
run(fake_input("BEGIN \n SET a 10 \n GET a \n BEGIN \n SET a 20 \n GET a \n ROLLBACK \n "
               "GET a \n ROLLBACK \n GET a \n END"))
run(fake_input("BEGIN \n SET a 30 \n BEGIN \n SET a 40 \n COMMIT \n GET a \n ROLLBACK \n END"))
run(fake_input("SET a 50 \n BEGIN \n GET a \n SET a 60 \n BEGIN \n UNSET a \n GET a \n "
               "ROLLBACK \n GET a \n COMMIT \n GET a \n END"))
run(fake_input("SET a 10 \n BEGIN \n NUMEQUALTO 10 \n BEGIN \n UNSET a \n NUMEQUALTO 10 \n "
               "ROLLBACK \n NUMEQUALTO 10 \n COMMIT \n END"))
run(fake_input())
"""
