package com.restaurant.staff.ui.order

import android.view.LayoutInflater
import android.view.ViewGroup
import androidx.recyclerview.widget.DiffUtil
import androidx.recyclerview.widget.ListAdapter
import androidx.recyclerview.widget.RecyclerView
import com.restaurant.staff.data.model.OrderItem
import com.restaurant.staff.databinding.ItemExistingOrderItemBinding

class ExistingOrderItemsAdapter(
    private val onQuantityChanged: (OrderItem, Int) -> Unit,
    private val onRemoveItem: (OrderItem) -> Unit
) : ListAdapter<OrderItem, ExistingOrderItemsAdapter.ViewHolder>(OrderItemDiffCallback()) {

    override fun submitList(list: List<OrderItem>?) {
        android.util.Log.d("ExistingOrderItemsAdapter", "submitList called with ${list?.size ?: 0} items")
        list?.forEach { item ->
            android.util.Log.d("ExistingOrderItemsAdapter", "Item in list: id=${item.id}, productId=${item.productId}, quantity=${item.quantity}")
        }

        // Debug current list before submit
        android.util.Log.d("ExistingOrderItemsAdapter", "Current list before submit: ${currentList.size} items")
        currentList.forEach { item ->
            android.util.Log.d("ExistingOrderItemsAdapter", "Current item: id=${item.id}, productId=${item.productId}, quantity=${item.quantity}")
        }

        // Force clear and submit to bypass DiffUtil issues
        super.submitList(null)
        super.submitList(list)

        android.util.Log.d("ExistingOrderItemsAdapter", "submitList completed, current item count: ${currentList.size}")

        // Force notify to debug
        notifyDataSetChanged()
        android.util.Log.d("ExistingOrderItemsAdapter", "notifyDataSetChanged called")
    }

    override fun onCreateViewHolder(parent: ViewGroup, viewType: Int): ViewHolder {
        android.util.Log.d("ExistingOrderItemsAdapter", "onCreateViewHolder called")
        val binding = ItemExistingOrderItemBinding.inflate(
            LayoutInflater.from(parent.context), parent, false
        )
        return ViewHolder(binding)
    }

    override fun onBindViewHolder(holder: ViewHolder, position: Int) {
        val item = getItem(position)
        android.util.Log.d("ExistingOrderItemsAdapter", "onBindViewHolder: position=$position, item=$item")
        holder.bind(item)
    }

    override fun getItemCount(): Int {
        val count = super.getItemCount()
        android.util.Log.d("ExistingOrderItemsAdapter", "getItemCount: $count")
        return count
    }

    inner class ViewHolder(
        private val binding: ItemExistingOrderItemBinding
    ) : RecyclerView.ViewHolder(binding.root) {

        fun bind(orderItem: OrderItem) {
            android.util.Log.d("ExistingOrderItemsAdapter", "Binding item: id=${orderItem.id}, name='${orderItem.itemName}', productId=${orderItem.productId}")
            binding.apply {
                textMenuItemName.text = orderItem.itemName ?: "Không xác định"
                textMenuItemPrice.text = "₫${String.format("%,.0f", orderItem.unitPrice)}"
                textQuantity.text = orderItem.quantity.toString()
                textTotalPrice.text = "₫${String.format("%,.0f", orderItem.totalPrice)}"

                // Set special instructions if available
                if (!orderItem.specialInstructions.isNullOrBlank()) {
                    textSpecialInstructions.text = "Ghi chú: ${orderItem.specialInstructions}"
                    textSpecialInstructions.visibility = android.view.View.VISIBLE
                } else {
                    textSpecialInstructions.visibility = android.view.View.GONE
                }

                // Setup quantity controls
                buttonDecrease.setOnClickListener {
                    val newQuantity = (orderItem.quantity ?: 0) - 1
                    if (newQuantity >= 0) {
                        onQuantityChanged(orderItem, newQuantity)
                    }
                }

                buttonIncrease.setOnClickListener {
                    val newQuantity = (orderItem.quantity ?: 0) + 1
                    onQuantityChanged(orderItem, newQuantity)
                }

                buttonRemove.setOnClickListener {
                    onRemoveItem(orderItem)
                }
            }
        }
    }

    private class OrderItemDiffCallback : DiffUtil.ItemCallback<OrderItem>() {
        override fun areItemsTheSame(oldItem: OrderItem, newItem: OrderItem): Boolean {
            // Use productId for comparison since id might be null for temporary items
            return oldItem.productId == newItem.productId
        }

        override fun areContentsTheSame(oldItem: OrderItem, newItem: OrderItem): Boolean {
            // Compare only the fields that matter for display
            return oldItem.itemName == newItem.itemName &&
                    oldItem.quantity == newItem.quantity &&
                    oldItem.unitPrice == newItem.unitPrice &&
                    oldItem.totalPrice == newItem.totalPrice &&
                    oldItem.specialInstructions == newItem.specialInstructions
        }
    }
}
