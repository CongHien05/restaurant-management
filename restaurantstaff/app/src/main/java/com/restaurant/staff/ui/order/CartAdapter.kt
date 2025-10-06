package com.restaurant.staff.ui.order

import android.view.LayoutInflater
import android.view.ViewGroup
import androidx.recyclerview.widget.DiffUtil
import androidx.recyclerview.widget.ListAdapter
import androidx.recyclerview.widget.RecyclerView
import com.restaurant.staff.data.model.CartItem
import com.restaurant.staff.databinding.ItemCartOrderBinding

class CartAdapter(
    private val onQuantityChanged: (CartItem, Int) -> Unit,
    private val onRemoveItem: (CartItem) -> Unit
) : ListAdapter<CartItem, CartAdapter.CartViewHolder>(CartDiffCallback()) {

    override fun onCreateViewHolder(parent: ViewGroup, viewType: Int): CartViewHolder {
        val binding = ItemCartOrderBinding.inflate(
            LayoutInflater.from(parent.context),
            parent,
            false
        )
        return CartViewHolder(binding, onQuantityChanged, onRemoveItem)
    }

    override fun onBindViewHolder(holder: CartViewHolder, position: Int) {
        holder.bind(getItem(position))
    }

    class CartViewHolder(
        private val binding: ItemCartOrderBinding,
        private val onQuantityChanged: (CartItem, Int) -> Unit,
        private val onRemoveItem: (CartItem) -> Unit
    ) : RecyclerView.ViewHolder(binding.root) {

        fun bind(cartItem: CartItem) {
            binding.apply {
                textCartItemName.text = cartItem.menuItem.name
                textCartItemPrice.text = "₫${String.format("%,.0f", cartItem.menuItem.price)}"
                textCartItemTotal.text = "₫${String.format("%,.0f", cartItem.menuItem.price * cartItem.quantity)}"

                // Set quantity
                textQuantity.text = cartItem.quantity.toString()

                // Setup quantity buttons
                buttonDecrease.setOnClickListener {
                    val newQuantity = cartItem.quantity - 1
                    onQuantityChanged(cartItem, newQuantity)
                    // Show feedback
                    com.google.android.material.snackbar.Snackbar.make(
                        root,
                        "Đã giảm số lượng ${cartItem.menuItem.name}",
                        com.google.android.material.snackbar.Snackbar.LENGTH_SHORT
                    ).show()
                }

                buttonIncrease.setOnClickListener {
                    val newQuantity = cartItem.quantity + 1
                    onQuantityChanged(cartItem, newQuantity)
                    // Show feedback
                    com.google.android.material.snackbar.Snackbar.make(
                        root,
                        "Đã tăng số lượng ${cartItem.menuItem.name}",
                        com.google.android.material.snackbar.Snackbar.LENGTH_SHORT
                    ).show()
                }

                // Setup remove button
                buttonRemove.setOnClickListener {
                    // Show confirmation dialog
                    com.google.android.material.dialog.MaterialAlertDialogBuilder(root.context)
                        .setTitle("Xóa món")
                        .setMessage("Bạn có chắc muốn xóa ${cartItem.menuItem.name} khỏi giỏ hàng?")
                        .setPositiveButton("Xóa") { _, _ ->
                            onRemoveItem(cartItem)
                            // Show feedback
                            com.google.android.material.snackbar.Snackbar.make(
                                root,
                                "Đã xóa ${cartItem.menuItem.name}",
                                com.google.android.material.snackbar.Snackbar.LENGTH_SHORT
                            ).show()
                        }
                        .setNegativeButton("Hủy", null)
                        .show()
                }

                // Disable decrease button if quantity is 1
                buttonDecrease.isEnabled = cartItem.quantity > 1
            }
        }
    }

    private class CartDiffCallback : DiffUtil.ItemCallback<CartItem>() {
        override fun areItemsTheSame(oldItem: CartItem, newItem: CartItem): Boolean {
            return oldItem.menuItem.id == newItem.menuItem.id
        }

        override fun areContentsTheSame(oldItem: CartItem, newItem: CartItem): Boolean {
            return oldItem == newItem
        }
    }
}
