"Simple in-memory database as a response to the Thumbtack coding challenge."

from collections import OrderedDict


class SimpleDb(object):

    "Simple in-memory database as a response to the Thumbtack coding challenge."

    def __init__(self):
        "Initialize SimpleDb instance."
        self.__db = {}
        self.__value_frequency = {}
        self.__num_open_transactions = 0
        self.__transactions = {}
        self.__transaction_value_frequency = {}

    def put(self, name, value):
        "Inserts/updates value of name in database."
        self.__update_value(name, value)

    def get(self, name):
        "Returns value of name if it exists in the database, otherwise returns None."
        if self.__isTransactionOpen() and name in self.__transactions:
            return self.__transactions[name][self.__getLastKey(self.__transactions[name])]
        elif name in self.__db:
            return self.__db[name]
        else:
            return None

    def get_num_equal_to(self, value):
        "Returns number of entries in the database that have the specified value."
        t_val_freq = self.__transaction_value_frequency
        return ((self.__value_frequency[value] if value in self.__value_frequency else 0) +
                (t_val_freq[value] if self.__isTransactionOpen() and value in t_val_freq else 0))

    def unset(self, name):
        "Removes name from database if it's present."
        self.__update_value(name, None)

    def begin(self):
        "Opens transaction block."
        self.__num_open_transactions += 1

    def rollback(self):
        "Rolls back most recent transaction, returning False if no open transactions."
        if not self.__isTransactionOpen():
            return False
        for name in self.__transactions.keys():
            if self.__num_open_transactions in self.__transactions[name]:
                value = self.__transactions[name].pop(self.__num_open_transactions)
                _ = self.__transactions[name] or self.__transactions.pop(name)
                self.__update_num_equal_to(value, self.get(name))
        self.__num_open_transactions -= 1
        return True

    def commit(self):
        "Commits all transactions, returning False if no open transactions."
        if not self.__isTransactionOpen():
            return False
        self.__num_open_transactions = 0
        any(self.__update_value(name, values[self.__getLastKey(values)])
            for name, values in self.__transactions.iteritems())
        self.__transactions = {}
        self.__transaction_value_frequency = {}
        return True

    def __getLastKey(self, ordered):
        "Returns key (or value if list, etc) most recently added to ordered."
        return next(reversed(ordered)) if ordered else None

    def __isTransactionOpen(self):
        "Returns True if there is currently a pending transaction. Returns False otherwise."
        return self.__num_open_transactions > 0

    def __update_num_equal_to(self, current_value, new_value=None):
        "Swaps current_value (lowers count by 1) with new_value (add 1). Skips None values."
        target = (self.__transaction_value_frequency if self.__isTransactionOpen() else
                  self.__value_frequency)
        for amount_to_add, value in [(-1, current_value), (1, new_value)]:
            if value is not None:
                target.setdefault(value, 0)
                target[value] += amount_to_add

    def __update_value(self, name, value):
        "Updates value (adding/editing/deleting) in database."
        current_value = self.get(name)
        if current_value == value:
            return
        elif self.__isTransactionOpen():
            self.__transactions.setdefault(name, OrderedDict())
            self.__transactions[name][self.__num_open_transactions] = value
        elif value is None:
            del self.__db[name]
        else:
            self.__db[name] = value
        self.__update_num_equal_to(current_value, value)


def display(value, default=None):
    "Prints value to stdout, or default if value is None and default is not None."
    print value if value is not None or default is None else default


OPS = {
    'GET':        (2, lambda db, name:        display(db.get(name), "NULL")),
    'NUMEQUALTO': (2, lambda db, value:       display(db.get_num_equal_to(value))),
    'UNSET':      (2, lambda db, name:        db.unset(name)),
    'BEGIN':      (1, lambda db:              db.begin()),
    'ROLLBACK':   (1, lambda db:              db.rollback() or display("NO TRANSACTION")),
    'COMMIT':     (1, lambda db:              db.commit() or display("NO TRANSACTION")),
    'END':        (1, lambda db:              False),
    'SET':        (3, lambda db, name, value: db.put(name, value)),
}


def process_command(simpleDb, command):
    "Applies command to the database. Returns False when stream of commands should end."
    command = command.split()
    opcode = command.pop(0).upper() if command else None
    if opcode is None or opcode not in OPS or len(command) != (OPS[opcode][0] - 1):
        print "INVALID COMMAND"
    elif 'END' == opcode:
        return False
    else:
        OPS[opcode][1](simpleDb, *command)
    return True


def run():
    "Reads commands from the command line and passes them through for processing."
    simpleDb = SimpleDb()
    all(iter(lambda: process_command(simpleDb, raw_input()), False))

run()
