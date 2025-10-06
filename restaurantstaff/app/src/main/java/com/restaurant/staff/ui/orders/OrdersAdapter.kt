package com.restaurant.staff.ui.orders

import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.TextView
import androidx.recyclerview.widget.DiffUtil
import androidx.recyclerview.widget.ListAdapter
import androidx.recyclerview.widget.RecyclerView
import com.restaurant.staff.R
import com.restaurant.staff.data.model.Order

class OrdersAdapter(
    private val onClick: (Order) -> Unit
) : ListAdapter<Order, OrdersAdapter.OrderViewHolder>(DiffCallback) {

    object DiffCallback : DiffUtil.ItemCallback<Order>() {
        override fun areItemsTheSame(oldItem: Order, newItem: Order): Boolean = oldItem.id == newItem.id
        override fun areContentsTheSame(oldItem: Order, newItem: Order): Boolean = oldItem == newItem
    }

    override fun onCreateViewHolder(parent: ViewGroup, viewType: Int): OrderViewHolder {
        val view = LayoutInflater.from(parent.context).inflate(R.layout.item_order, parent, false)
        return OrderViewHolder(view, onClick)
    }

    override fun onBindViewHolder(holder: OrderViewHolder, position: Int) {
        holder.bind(getItem(position))
    }

    class OrderViewHolder(itemView: View, val onClick: (Order) -> Unit) : RecyclerView.ViewHolder(itemView) {
        private val textOrderNumber: TextView = itemView.findViewById(R.id.text_order_number)
        private val textTable: TextView = itemView.findViewById(R.id.text_table)
        private val textStatus: TextView = itemView.findViewById(R.id.text_status)
        private val textTotal: TextView = itemView.findViewById(R.id.text_total)
        private var current: Order? = null

        init {
            itemView.setOnClickListener { current?.let(onClick) }
        }

        fun bind(order: Order) {
            current = order
            textOrderNumber.text = "#${order.orderNumber}"
            textTable.text = "Bàn ${order.tableId}"
            textStatus.text = order.statusText
            textStatus.setTextColor(order.statusColor)
            textTotal.text = order.formattedTotal
        }
    }
}


