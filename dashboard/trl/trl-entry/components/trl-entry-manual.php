<section class="entry-block" id="manualModeBlock">
    <form id="manualEntryForm" method="post" action="controllers/trl-entry-insert.php" class="entry-form auto-entry-form manual-entry-form" novalidate>
        <input type="hidden" name="source_mode" value="manual">

        <div class="auto-content-grid">
            <!-- Left Column: Editable Transaction Details -->
            <div class="auto-data-column">
                <div class="auto-data-header">
                    <span class="material-icons">folder_open</span>
                    <h3>Transaction Details (Manual)</h3>
                </div>
                <div class="auto-data-card">
                    <div class="data-item">
                        <div class="data-icon"><span class="material-icons">confirmation_number</span></div>
                        <div class="data-content">
                            <span class="data-label">Reference No.</span>
                            <input id="mRefNo" name="ref_no" class="data-value field-input required-field" type="text" placeholder="Enter reference number" required>
                        </div>
                    </div>

                    <div class="data-item">
                        <div class="data-icon"><span class="material-icons">schedule</span></div>
                        <div class="data-content">
                            <span class="data-label">Transaction Date/Time</span>
                            <input id="mTransDate" name="transfer_datetime" class="data-value field-input required-field" type="text" placeholder="YYYY-MM-DD HH:MM:SS" required>
                        </div>
                    </div>

                    <div class="data-item">
                        <div class="data-icon"><span class="material-icons">account_balance</span></div>
                        <div class="data-content">
                            <span class="data-label">Account Number</span>
                            <input id="mAccountNo" name="account_no" class="data-value field-input required-field" type="text" placeholder="Enter account number" required>
                        </div>
                    </div>

                    <div class="data-item">
                        <div class="data-icon"><span class="material-icons">person</span></div>
                        <div class="data-content">
                            <span class="data-label">Account Name</span>
                            <input id="mName" name="name" class="data-value field-input required-field" type="text" placeholder="Enter account name" required>
                        </div>
                    </div>

                    <div class="data-item">
                        <div class="data-icon"><span class="material-icons">business</span></div>
                        <div class="data-content">
                            <span class="data-label">Branch ID</span>
                            <input id="mBranchId" name="payment_branch_id" class="data-value field-input required-field" type="text" placeholder="Enter branch ID" required>
                        </div>
                    </div>

                    <div class="data-item">
                        <div class="data-icon"><span class="material-icons">store</span></div>
                        <div class="data-content">
                            <span class="data-label">Payment Branch</span>
                            <input id="mBranchName" name="payment_branch_name" class="data-value field-input required-field" type="text" placeholder="Enter branch name" required>
                        </div>
                    </div>

                    <div class="data-item">
                        <div class="data-icon"><span class="material-icons">warning</span></div>
                        <div class="data-content">
                            <span class="data-label">Biller ID</span>
                            <input id="mBillerId" name="wrong_biller_id" class="data-value field-input required-field" type="text" placeholder="Enter biller id" required>
                        </div>
                    </div>

                    <div class="data-item">
                        <div class="data-icon"><span class="material-icons">business</span></div>
                        <div class="data-content">
                            <span class="data-label">Biller Name</span>
                            <input id="mBillerName" name="biller_name" class="data-value field-input required-field" type="text" placeholder="Enter biller name" required>
                        </div>
                    </div>

                    <div class="data-item">
                        <div class="data-icon"><span class="material-icons">attach_money</span></div>
                        <div class="data-content">
                            <span class="data-label">Amount</span>
                            <input id="mAmount" name="amount" class="data-value field-input required-field" type="number" min="0" step="0.01" placeholder="0.00" required>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Request Information -->
            <div class="auto-input-column">
                <div class="auto-input-header">
                    <span class="material-icons">edit_note</span>
                    <h3>Request Information</h3>
                </div>
                <div class="auto-input-card">
                    <div class="field-group">
                        <label for="mTypeRequest"><span class="material-icons">category</span> Type of Request</label>
                        <select id="mTypeRequest" name="type_of_request" class="field-input required-field" required>
                            <option value="">Select request type</option>
                            <option>NO PAYMENT RECEIVED</option>
                            <option>DOUBLE POSTING</option>
                            <option>MULTI POSTING</option>
                            <option>TRIPLE POSTING</option>
                            <option>WRONG BILLER</option>
                            <option>OVERSTATED AMOUNT</option>
                            <option>CANCELLED TRANSACTION</option>
                            <option>UNREFLECTED TRXN</option>
                        </select>
                    </div>

                    <!-- Biller info moved to Transaction Details (manual) -->

                    <!-- OVERSTATED AMOUNT supplemental inputs -->
                    <div class="field-group overstated-group" style="display:none;">
                        <label for="mWrongAmount"><span class="material-icons">payments</span> Wrong Amount</label>
                        <input id="mWrongAmount" name="wrong_amount" class="field-input currency-input" type="text" inputmode="decimal" pattern="[0-9,\.\-]*" placeholder="0.00">
                    </div>

                    <div class="field-group overstated-group" style="display:none;">
                        <label for="mCorrectAmount"><span class="material-icons">payments</span> Correct Amount</label>
                        <input id="mCorrectAmount" name="correct_amount" class="field-input currency-input" type="text" inputmode="decimal" pattern="[0-9,\.\-]*" placeholder="0.00">
                    </div>

                    <div class="field-group overstated-group" style="display:none;">
                        <label for="mDifferenceValue"><span class="material-icons">calculate</span> Difference</label>
                        <input id="mDifferenceValue" name="difference_value" class="field-input currency-input" type="text" readonly placeholder="0.00">
                    </div>

                    <div class="field-group">
                        <label for="mCorrectBillerId"><span class="material-icons">check_circle</span> Correct Biller ID</label>
                        <input id="mCorrectBillerId" name="correct_biller_id" class="field-input required-field" type="text" placeholder="Enter correct biller ID" required>
                    </div>

                    <div class="field-group">
                        <label for="mCorrectBillerName"><span class="material-icons">business</span> Correct Biller Name</label>
                        <input id="mCorrectBillerName" name="correct_biller_name" class="field-input required-field" type="text" placeholder="Enter correct biller name" required>
                    </div>

                    <div class="field-group field-fullwidth">
                        <label for="mReason"><span class="material-icons">description</span> Reason for Request</label>
                        <textarea id="mReason" name="reason" class="field-input required-field" rows="4" placeholder="Provide detailed reason for this transaction request log entry" required></textarea>
                    </div>

                </div>
            </div>
        </div>
    </form>
</section>
