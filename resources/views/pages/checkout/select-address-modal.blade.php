<div class="modal fade quickview-modal-style" id="select-address-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered" role="document">

        <div class="modal-content">
            <div class="modal-body">
                <div class="modal-title">
                    <h4>My Address</h4>
                </div>
                <div class="address-modal-content">
                    <div class="address-list">
                        @foreach (Auth::user()->addresses as $address)
                            <div class="address-item" data-receiver-name="{{ $address->receiver_name }}"
                                data-phone-number="{{ $address->phone_number }}"
                                data-address="{{ $address->address }}">
                                <div>
                                    <label class="custom-radio">
                                        <input type="radio" name="checkout_address_id"
                                            value="{{ $address->address_id }}"
                                            @if ($address->is_default) checked @endif>
                                        <span class="checkmark"></span>
                                    </label>
                                </div>
                                <div>
                                    <div class="heading">
                                        <p>{{ $address->receiver_name }}</p>
                                        <div class="separate"></div>
                                        <p>{{ $address->phone_number }}</p>
                                    </div>
                                    <p>{{ $address->address }}</p>
                                    @if ($address->is_default)
                                        <div class="default-tag">
                                            Default
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endforeach

                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="cancel" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="js-change-address submit" data-bs-dismiss="modal">Save changes</button>
                </div>
            </div>
        </div>
    </div>
</div>
